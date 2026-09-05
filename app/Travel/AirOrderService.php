<?php

namespace App\Travel;

use App\Mail\BookingConfirmation;
use App\Models\AnalyticsEvent;
use App\Models\Addon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\TravelOffer;
use App\Travel\TravelApi\TravelApiClient;
use App\Travel\TravelApi\TravelApiAtpcoBookingRequestBuilder;
use App\Travel\TravelApi\TravelApiTripOrderRequestBuilder;
use App\Travel\Pricing\ExchangeRateService;
use App\Travel\Pricing\OperatorMarkupCalculator;
use App\Support\TravelLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use RuntimeException;

final class AirOrderService
{
    public function __construct(
        private readonly TravelApiClient $client,
        private readonly TravelApiTripOrderRequestBuilder $requestBuilder,
        private readonly TravelApiAtpcoBookingRequestBuilder $atpcoRequestBuilder,
        private readonly TravelLogger $travelLogger,
        private readonly ExchangeRateService $rates,
        private readonly OperatorMarkupCalculator $operatorMarkup,
    ) {}

    /** @param array<int, array<string, mixed>> $travellers */
    public function create(TravelOffer $offer, Customer $customer, array $travellers, ?string $agencyNumber = null, Collection|array $addons = [], array $manualMarkup = [], bool $sendConfirmation = true): Order
    {
        $offer->loadMissing('flightSearch');
        if (! $offer->last_validated_at || $offer->last_validated_at->lt(now()->subMinutes(15))) {
            throw new RuntimeException('This fare must be revalidated again before booking.');
        }
        if ($offer->expires_at->isPast()) {
            throw new RuntimeException('This fare has expired. Please search again.');
        }

        // Add-ons and operator adjustments belong to the local Karossy order only.
        // Prepare them before contacting the airline so a local pricing problem can
        // never occur after a remote reservation has already been created.
        $addons = collect($addons)->filter(fn ($addon): bool => $addon instanceof Addon && $addon->active && $addon->type === 'flight');
        $addonRows = $addons->mapWithKeys(function (Addon $addon) use ($offer): array {
            $converted = $this->rates->convertMinor($addon->price_cents, $addon->currency, $offer->currency);

            return [$addon->id => ['id' => (string) Str::uuid(), 'quantity' => 1, 'price_cents' => $converted['amount_minor'], 'currency' => $converted['currency']]];
        });
        $addonTotalMinor = (int) $addonRows->sum('price_cents');
        $operatorMarkup = $this->operatorMarkup->calculate(
            $offer->selling_total_minor + $addonTotalMinor,
            $manualMarkup['type'] ?? null,
            $manualMarkup['value'] ?? null,
        );

        try {
            if ($offer->provider === 'fake') {
                $providerResponse = ['order' => ['id' => 'TEST-'.Str::upper(Str::random(7))]];
            } else {
                $isNdc = ! blank(data_get($offer->fare_summary, 'order_offer_id'));

                if ($isNdc) {
                    $airlinePayload = $this->requestBuilder->build($offer, $customer, $travellers);
                    $providerResponse = $this->client->createTripOrder($airlinePayload);
                } else {
                    $airlinePayload = $this->atpcoRequestBuilder->build($offer, $customer, $travellers, $agencyNumber);
                    $providerResponse = $this->client->createAtpcoBooking($airlinePayload);
                }
            }
        } catch (\Throwable $exception) {
            $this->travelLogger->record('flight', 'booking', $offer->provider, [
                'offer_id' => $offer->id, 'customer_id' => $customer->id,
                'traveller_count' => count($travellers),
            ], [], ['status' => 'failed', 'session_id' => $offer->flightSearch->session_id, 'offer_id' => $offer->id, 'error_message' => $exception->getMessage()]);
            throw $exception;
        }
        $locator = $this->locator($providerResponse);
        if ($locator === '') {
            $this->travelLogger->record('flight', 'booking', $offer->provider, ['offer_id' => $offer->id], [
                'provider_response_received' => true, 'locator_found' => false,
            ], ['status' => 'failed', 'session_id' => $offer->flightSearch->session_id, 'offer_id' => $offer->id, 'error_message' => 'The travel API response did not contain a booking locator.']);
            throw new RuntimeException('The travel system did not return a booking locator. No local booking was confirmed.');
        }

        $order = DB::transaction(function () use ($offer, $customer, $travellers, $providerResponse, $locator, $addonRows, $addonTotalMinor, $operatorMarkup): Order {
            $order = Order::create([
                'reference' => 'KAR-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'user_id' => auth()->id(),
                'customer_id' => $customer->id,
                'channel' => auth()->user()?->isB2b()
                    ? 'b2b'
                    : (auth()->user()?->isAdmin() ? 'admin' : 'consumer'),
                'status' => 'confirmed',
                'currency' => $offer->currency,
                'subtotal_minor' => $offer->selling_total_minor,
                'fees_minor' => $addonTotalMinor + $operatorMarkup['amount_minor'],
                'operator_markup_type' => $operatorMarkup['type'],
                'operator_markup_value' => $operatorMarkup['value'],
                'operator_markup_minor' => $operatorMarkup['amount_minor'],
                'total_minor' => $offer->selling_total_minor + $addonTotalMinor + $operatorMarkup['amount_minor'],
                'customer' => ['name' => $customer->full_name, 'email' => $customer->email, 'phone' => $customer->phone],
                'expires_at' => $offer->expires_at,
            ]);
            $booking = $order->bookings()->create([
                'travel_offer_id' => $offer->id,
                'product_type' => 'flight',
                'provider' => $offer->provider,
                'provider_locator' => $locator,
                'status' => 'confirmed',
                'source' => $this->bookingSource(),
                'referrer' => request()->headers->get('referer'),
                'utm_source' => request()->hasSession() ? request()->session()->get('attribution.utm_source') : null,
                'utm_medium' => request()->hasSession() ? request()->session()->get('attribution.utm_medium') : null,
                'utm_campaign' => request()->hasSession() ? request()->session()->get('attribution.utm_campaign') : null,
                'travellers' => $travellers,
                'details' => [
                    'itinerary' => $offer->itinerary,
                    'provider_response' => $providerResponse,
                    'pricing' => [
                        'base_minor' => $offer->provider_total_minor,
                        'configured_markup_minor' => $offer->markup_minor,
                        'addons_minor' => $addonTotalMinor,
                        'operator_markup_minor' => $operatorMarkup['amount_minor'],
                    ],
                ],
                'booked_at' => now(),
            ]);
            if ($addonRows->isNotEmpty()) {
                $booking->addons()->attach($addonRows->all());
            }

            return $order;
        });

        AnalyticsEvent::create([
            'event' => 'flight_booking_created',
            'service' => 'flights',
            'funnel_step' => 'booking_created',
            'session_id' => $offer->flightSearch->session_id,
            'source' => $offer->provider,
            'properties' => ['offer_id' => $offer->id, 'order_id' => $order->id, 'channel' => $order->channel, 'addon_count' => $addons->count(), 'source' => $this->bookingSource()],
            'occurred_at' => now(),
        ]);

        $booking = $order->bookings->first();
        $this->travelLogger->record('flight', 'booking', $offer->provider, [
            'offer_id' => $offer->id,
            'customer_id' => $customer->id,
            'traveller_count' => count($travellers),
            'addon_ids' => $addons->pluck('id')->all(),
            'channel' => $order->channel,
        ], [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'provider_locator' => $booking?->provider_locator,
            'status' => $booking?->status,
        ], ['session_id' => $offer->flightSearch->session_id, 'offer_id' => $offer->id, 'order_id' => $order->id]);

        if ($sendConfirmation) {
            $this->sendConfirmation($order);
        }

        return $order->load('bookings.addons');
    }

    public function sendConfirmation(Order $order): void
    {
        $order->loadMissing('bookings.addons');
        $customerEmail = data_get($order->customer, 'email');
        if (filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $booking = $order->bookings->first();
            try {
                Mail::to($customerEmail)
                    ->send(new BookingConfirmation($order, $booking));
            } catch (\Throwable $e) {
                // Mail failure must never roll back a confirmed booking
                Log::error('Failed to send booking confirmation email.', [
                    'order_id' => $order->id,
                    'email' => $customerEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function bookingSource(): string
    {
        if (auth()->user()?->isAdmin()) return 'admin';
        if (auth()->user()?->isB2b()) return 'b2b_portal';
        if (request()->header('X-Client-Platform') === 'mobile') return 'mobile_app';

        return 'website';
    }

    /** @param array<string, mixed> $response */
    private function locator(array $response): string
    {
        // NDC Trip Orders API paths
        // ATPCO Booking Management API paths
        foreach ([
            'order.id',
            'orders.0.id',
            'data.order.id',
            // createBooking response paths
            'booking.reservationIds.0.reservationId',
            'booking.id',
            'reservationId',
            'confirmationId',
            'pnr',
            'CreatePassengerNameRecordRS.ItineraryRef.ID',
        ] as $path) {
            $value = data_get($response, $path);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        // Fallback: scan the entire response for any plausible locator strings
        $candidates = [];
        $this->collectStrings($response, $candidates);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && preg_match('/^[0-9A-Z]{6,8}$/', $candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /** Collect all scalar string values from nested response into array */
    private function collectStrings(mixed $data, array &$out): void
    {
        if (is_string($data)) {
            $out[] = trim($data);

            return;
        }

        if (is_array($data)) {
            foreach ($data as $v) {
                $this->collectStrings($v, $out);
            }
        }
    }
}

<?php

namespace App\Travel;

use App\Mail\BookingConfirmation;
use App\Mail\PaymentReceipt;
use App\Models\AnalyticsEvent;
use App\Models\Addon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\TravelOffer;
use App\Models\User;
use App\Travel\TravelApi\TravelApiClient;
use App\Travel\Exceptions\BookingCreationException;
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

    /** @param array<int, array<string, mixed>> $travellers @param array<string, mixed> $contact */
    public function create(TravelOffer $offer, Customer $customer, array $travellers, ?string $agencyNumber = null, Collection|array $addons = [], array $manualMarkup = [], bool $sendConfirmation = true, array $contact = [], ?int $ownerUserId = null): Order
    {
        $offer->loadMissing('flightSearch');
        if (! $offer->last_validated_at || $offer->last_validated_at->lt(now()->subMinutes(15))) {
            throw new BookingCreationException(
                'availability',
                'The fare became stale before the airline reservation could be completed. Your payment is safe and Karossy is reviewing the booking.',
                'This fare must be revalidated again before booking.',
            );
        }
        if ($offer->expires_at->isPast()) {
            throw new BookingCreationException(
                'availability',
                'The fare expired before the airline reservation could be completed. Your payment is safe and Karossy is reviewing the booking.',
                'This fare has expired. Please search again.',
            );
        }

        $bookingContact = $this->bookingContact($customer, $travellers, $contact);
        $providerCustomer = new Customer([
            'first_name' => (string) data_get($travellers, '0.first_name', $customer->first_name),
            'last_name' => (string) data_get($travellers, '0.last_name', $customer->last_name),
            'email' => $bookingContact['email'],
            'phone' => $bookingContact['phone'],
        ]);

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
                    $airlinePayload = $this->requestBuilder->build($offer, $providerCustomer, $travellers);
                    $providerResponse = $this->client->createTripOrder($airlinePayload);
                } else {
                    $airlinePayload = $this->atpcoRequestBuilder->build($offer, $providerCustomer, $travellers, $agencyNumber);
                    $providerResponse = $this->client->createAtpcoBooking($airlinePayload);
                }
            }
        } catch (\Throwable $exception) {
            $this->travelLogger->record('flight', 'booking', $offer->provider, [
                'offer_id' => $offer->id, 'customer_id' => $customer->exists ? $customer->id : null,
                'traveller_count' => count($travellers),
            ], [], ['status' => 'failed', 'session_id' => $offer->flightSearch->session_id, 'offer_id' => $offer->id, 'error_message' => $exception->getMessage()]);

            throw new BookingCreationException(
                'supplier',
                'The airline could not confirm the reservation. Your payment is confirmed and Karossy is reviewing the supplier response.',
                $exception->getMessage(),
                previous: $exception,
            );
        }
        $locator = $this->locator($providerResponse);
        if ($locator === '') {
            $this->travelLogger->record('flight', 'booking', $offer->provider, ['offer_id' => $offer->id], [
                'provider_response_received' => true, 'locator_found' => false,
            ], ['status' => 'failed', 'session_id' => $offer->flightSearch->session_id, 'offer_id' => $offer->id, 'error_message' => 'The travel API response did not contain a booking locator.']);
            throw new BookingCreationException(
                'supplier',
                'The airline response did not include a usable PNR. Your payment is confirmed and Karossy is reviewing the reservation.',
                'The travel API response did not contain a booking locator.',
            );
        }

        try {
            $order = DB::transaction(function () use ($offer, $customer, $travellers, $providerResponse, $locator, $addonRows, $addonTotalMinor, $operatorMarkup, $bookingContact, $ownerUserId): Order {
            $order = Order::create([
                'reference' => 'KAR-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'user_id' => $ownerUserId ?? auth()->id(),
                'customer_id' => $customer->exists ? $customer->id : null,
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
                'customer' => $bookingContact,
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
                'utm_source' => request()->session()->get('attribution.utm_source'),
                'utm_medium' => request()->session()->get('attribution.utm_medium'),
                'utm_campaign' => request()->session()->get('attribution.utm_campaign'),
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
        } catch (\Throwable $exception) {
            $this->travelLogger->record('flight', 'booking_persistence', 'karossy', [
                'offer_id' => $offer->id,
                'provider_locator' => $locator,
            ], [], [
                'status' => 'failed',
                'session_id' => $offer->flightSearch->session_id,
                'offer_id' => $offer->id,
                'error_message' => $exception->getMessage(),
            ]);

            throw new BookingCreationException(
                'persistence',
                'The airline returned a PNR, but Karossy could not finish saving the booking. Do not pay again; support is reconciling it now.',
                $exception->getMessage(),
                $locator,
                $exception,
            );
        }

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
            'customer_id' => $customer->exists ? $customer->id : null,
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


    public function sendReceipt(Order $order): void
    {
        $order->loadMissing('payments');
        $payment = $order->payments
            ->sortByDesc('paid_at')
            ->first(fn ($payment): bool => in_array(strtolower((string) $payment->status), ['paid', 'simulated'], true));

        if (! $payment) {
            return;
        }

        $receiptEmail = $order->user_id
            ? User::query()->whereKey($order->user_id)->value('email')
            : data_get($order->customer, 'email');

        if (! filter_var($receiptEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($receiptEmail)->send(new PaymentReceipt($order, $payment));
        } catch (\Throwable $e) {
            Log::error('Failed to send payment receipt email.', [
                'order_id' => $order->id,
                'email' => $receiptEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $travellers @param array<string, mixed> $contact @return array{name:string,email:string,phone:?string} */
    private function bookingContact(Customer $customer, array $travellers, array $contact): array
    {
        $email = strtolower(trim((string) ($contact['email'] ?? $customer->email)));
        $phone = isset($contact['phone']) ? trim((string) $contact['phone']) : $customer->phone;
        $name = trim((string) ($contact['name'] ?? ''));

        if ($name === '') {
            $name = trim(collect([
                data_get($travellers, '0.title'),
                data_get($travellers, '0.first_name'),
                data_get($travellers, '0.last_name'),
            ])->filter()->implode(' '));
        }
        if ($name === '') {
            $name = $customer->full_name ?: 'Valued Traveller';
        }

        return ['name' => $name, 'email' => $email, 'phone' => $phone];
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
            'booking.bookingId',
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

        return '';
    }
}

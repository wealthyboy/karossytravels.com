<?php

namespace App\Travel;

use App\Mail\BookingConfirmation;
use App\Models\Addon;
use App\Models\AnalyticsEvent;
use App\Models\Customer;
use App\Models\HotelOffer;
use App\Models\Order;
use App\Support\TravelLogger;
use App\Travel\Pricing\ExchangeRateService;
use App\Travel\Pricing\OperatorMarkupCalculator;
use App\Travel\TravelApi\TravelApiClient;
use App\Travel\TravelApi\TravelApiHotelBookingRequestBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

final class HotelOrderService
{
    public function __construct(
        private readonly TravelApiClient $client,
        private readonly TravelApiHotelBookingRequestBuilder $requestBuilder,
        private readonly ExchangeRateService $rates,
        private readonly OperatorMarkupCalculator $operatorMarkup,
        private readonly TravelLogger $travelLogger,
    ) {}

    /**
     * Re-check the live hotel rate and make sure Karossy can satisfy the
     * supplier's guarantee/payment requirements before taking payment.
     *
     * @return array<string, mixed>
     */
    public function preflight(HotelOffer $offer): array
    {
        $offer->loadMissing('search');
        $this->assertOfferUsable($offer);

        if ($offer->provider === 'fake') {
            return ['provider' => 'fake', 'bookable' => true];
        }

        return $this->priceCheck($offer);
    }

    public function create(HotelOffer $offer, Customer $customer, Collection|array $addons = [], array $manualMarkup = [], ?string $specialRequests = null, bool $sendConfirmation = true): Order
    {
        $offer->loadMissing('search');
        $this->assertOfferUsable($offer);

        // Local add-ons and operator markup are calculated before touching the
        // supplier. A local pricing failure must never happen after Sabre has
        // already committed a hotel reservation.
        $addons = collect($addons)->filter(fn ($addon) => $addon instanceof Addon && $addon->active && $addon->type === 'hotel');
        $addonRows = $addons->mapWithKeys(function (Addon $addon) use ($offer): array {
            $converted = $this->rates->convertMinor($addon->price_cents, $addon->currency, $offer->currency);

            return [$addon->id => [
                'id' => (string) Str::uuid(),
                'quantity' => 1,
                'price_cents' => $converted['amount_minor'],
                'currency' => $offer->currency,
            ]];
        });
        $addonTotal = (int) $addonRows->sum('price_cents');
        $operatorMarkup = $this->operatorMarkup->calculate(
            $offer->selling_total_minor + $addonTotal,
            $manualMarkup['type'] ?? null,
            $manualMarkup['value'] ?? null,
        );

        $priceCheckResponse = [];
        $providerResponse = [];
        $locator = '';
        $hotelConfirmation = null;

        try {
            if ($offer->provider === 'fake') {
                $locator = 'HTL-'.Str::upper(Str::random(7));
                $hotelConfirmation = $locator;
                $providerResponse = [
                    'CreatePassengerNameRecordRS' => [
                        'ApplicationResults' => ['status' => 'Complete'],
                        'ItineraryRef' => ['ID' => $locator],
                    ],
                ];
            } else {
                // Sabre CSL requires a fresh Hotel Price Check before booking.
                // The price check turns the shopping RateKey into a BookingKey.
                $priceCheckResponse = $this->priceCheck($offer);
                $bookingKey = $this->bookingKey($priceCheckResponse);

                $payload = $this->requestBuilder->booking(
                    $offer,
                    $customer,
                    $bookingKey,
                    $priceCheckResponse,
                    $specialRequests,
                );

                $startedAt = microtime(true);
                try {
                    $providerResponse = $this->client->createHotelReservation($payload);
                } catch (\Throwable $exception) {
                    $this->travelLogger->record('hotel', 'booking', $offer->provider, [
                        'offer_id' => $offer->id,
                        'customer_id' => $customer->id,
                        'booking_key_present' => $bookingKey !== '',
                    ], [], [
                        'status' => 'failed',
                        'session_id' => $offer->search->session_id,
                        'offer_id' => $offer->id,
                        'duration_ms' => $this->durationMs($startedAt),
                        'error_message' => $exception->getMessage(),
                    ]);
                    throw $exception;
                }

                if ($applicationError = $this->applicationError($providerResponse, 'CreatePassengerNameRecordRS')) {
                    $this->travelLogger->record('hotel', 'booking', $offer->provider, [
                        'offer_id' => $offer->id,
                        'customer_id' => $customer->id,
                    ], [
                        'application_status' => data_get($providerResponse, 'CreatePassengerNameRecordRS.ApplicationResults.status'),
                    ], [
                        'status' => 'failed',
                        'session_id' => $offer->search->session_id,
                        'offer_id' => $offer->id,
                        'duration_ms' => $this->durationMs($startedAt),
                        'error_message' => $applicationError,
                    ]);
                    throw new RuntimeException('The hotel supplier could not confirm the reservation: '.$applicationError);
                }

                $locator = $this->locator($providerResponse);
                $hotelConfirmation = $this->hotelConfirmation($providerResponse);

                if ($locator === '') {
                    $message = 'The hotel supplier response did not contain a PNR/locator. No local hotel booking was confirmed.';
                    $this->travelLogger->record('hotel', 'booking', $offer->provider, [
                        'offer_id' => $offer->id,
                        'customer_id' => $customer->id,
                    ], [
                        'provider_response_received' => true,
                        'locator_found' => false,
                    ], [
                        'status' => 'failed',
                        'session_id' => $offer->search->session_id,
                        'offer_id' => $offer->id,
                        'duration_ms' => $this->durationMs($startedAt),
                        'error_message' => $message,
                    ]);
                    throw new RuntimeException($message);
                }

                $this->travelLogger->record('hotel', 'booking', $offer->provider, [
                    'offer_id' => $offer->id,
                    'customer_id' => $customer->id,
                    'booking_key_present' => true,
                ], [
                    'provider_locator' => $locator,
                    'hotel_confirmation' => $hotelConfirmation,
                    'application_status' => data_get($providerResponse, 'CreatePassengerNameRecordRS.ApplicationResults.status'),
                ], [
                    'session_id' => $offer->search->session_id,
                    'offer_id' => $offer->id,
                    'duration_ms' => $this->durationMs($startedAt),
                ]);
            }
        } catch (\Throwable $exception) {
            // priceCheck() and the live booking call record their exact stage.
            // This summary entry makes the failed booking visible even when the
            // failure happened before a local Order existed.
            if ($offer->provider !== 'fake') {
                $this->travelLogger->record('hotel', 'booking_summary', $offer->provider, [
                    'offer_id' => $offer->id,
                    'customer_id' => $customer->id,
                ], [], [
                    'status' => 'failed',
                    'session_id' => $offer->search->session_id,
                    'offer_id' => $offer->id,
                    'error_message' => $exception->getMessage(),
                ]);
            }

            throw $exception;
        }

        // Only create a local confirmed booking after the supplier has returned
        // a PNR/locator. Real supplier bookings no longer enter a false pending
        // state simply because the booking API was never called.
        $order = DB::transaction(function () use ($offer, $customer, $addonRows, $addonTotal, $operatorMarkup, $locator, $hotelConfirmation, $priceCheckResponse, $providerResponse, $specialRequests): Order {
            $order = Order::create([
                'reference' => 'KAR-H-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'user_id' => auth()->id(),
                'customer_id' => $customer->id,
                'channel' => auth()->user()?->isB2b() ? 'b2b' : (auth()->user()?->isAdmin() ? 'admin' : 'consumer'),
                'status' => 'confirmed',
                'currency' => $offer->currency,
                'subtotal_minor' => $offer->selling_total_minor,
                'fees_minor' => $addonTotal + $operatorMarkup['amount_minor'],
                'operator_markup_type' => $operatorMarkup['type'],
                'operator_markup_value' => $operatorMarkup['value'],
                'operator_markup_minor' => $operatorMarkup['amount_minor'],
                'total_minor' => $offer->selling_total_minor + $addonTotal + $operatorMarkup['amount_minor'],
                'customer' => ['name' => $customer->full_name, 'email' => $customer->email, 'phone' => $customer->phone],
                'expires_at' => $offer->expires_at,
            ]);

            $booking = $order->bookings()->create([
                'product_type' => 'hotel',
                'provider' => $offer->provider,
                'provider_locator' => $locator,
                'status' => 'confirmed',
                'source' => $this->source(),
                'referrer' => request()->headers->get('referer'),
                'travellers' => [['name' => $customer->full_name, 'email' => $customer->email]],
                'details' => [
                    'hotel_offer_id' => $offer->id,
                    'stay' => [
                        'hotel_name' => $offer->name,
                        'hotel_code' => $offer->hotel_code,
                        'check_in' => $offer->search->check_in->toDateString(),
                        'check_out' => $offer->search->check_out->toDateString(),
                        'rooms' => $offer->search->rooms,
                        'adults' => $offer->search->adults,
                        'children' => $offer->search->children,
                        'room_name' => $offer->room_name,
                        'rate_name' => $offer->rate_name,
                    ],
                    'special_requests' => $specialRequests,
                    'pricing' => [
                        'base_minor' => $offer->provider_total_minor,
                        'configured_markup_minor' => $offer->markup_minor,
                        'addons_minor' => $addonTotal,
                        'operator_markup_minor' => $operatorMarkup['amount_minor'],
                    ],
                    'provider_confirmation_required' => false,
                    'hotel_confirmation' => $hotelConfirmation,
                    'price_check' => $this->priceCheckSummary($priceCheckResponse),
                    'provider_response' => $this->providerResponseSummary($providerResponse),
                ],
                'booked_at' => now(),
            ]);

            if ($addonRows->isNotEmpty()) {
                $booking->addons()->attach($addonRows->all());
            }

            return $order;
        })->load('bookings.addons');

        AnalyticsEvent::create([
            'event' => 'hotel_booking_created',
            'service' => 'hotels',
            'funnel_step' => 'booking_created',
            'session_id' => $offer->search->session_id,
            'source' => $offer->provider,
            'properties' => ['order_id' => $order->id, 'channel' => $order->channel, 'status' => 'confirmed'],
            'occurred_at' => now(),
        ]);

        // Fake-provider bookings do not have a supplier call above, so record
        // their local completion here. Real supplier completion was already
        // logged with the remote response timing before the database write.
        if ($offer->provider === 'fake') {
            $this->travelLogger->record('hotel', 'booking', $offer->provider, [
                'offer_id' => $offer->id,
                'customer_id' => $customer->id,
                'addon_ids' => $addons->pluck('id')->all(),
            ], [
                'order_id' => $order->id,
                'status' => 'confirmed',
                'provider_locator' => $locator,
            ], [
                'session_id' => $offer->search->session_id,
                'order_id' => $order->id,
            ]);
        }

        if ($sendConfirmation) {
            $this->sendConfirmation($order);
        }

        return $order;
    }

    public function sendConfirmation(Order $order): void
    {
        $order->loadMissing(['bookings.addons']);
        $booking = $order->bookings->first();
        $email = data_get($order->customer, 'email');

        if (! $booking || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($email)->send(new BookingConfirmation($order, $booking));
        } catch (\Throwable $exception) {
            Log::error('Failed to send hotel booking email.', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function assertOfferUsable(HotelOffer $offer): void
    {
        if ($offer->expires_at->isPast()) {
            throw new RuntimeException('This hotel rate has expired. Search again.');
        }

        if ($offer->provider !== 'fake' && blank($offer->rate_key)) {
            throw new RuntimeException('This hotel offer does not contain the supplier rate key required for confirmation. Search again.');
        }
    }

    /** @return array<string, mixed> */
    private function priceCheck(HotelOffer $offer): array
    {
        $payload = $this->requestBuilder->priceCheck($offer);
        $startedAt = microtime(true);

        try {
            $response = $this->client->priceCheckHotel($payload);
        } catch (\Throwable $exception) {
            $this->travelLogger->record('hotel', 'price_check', $offer->provider, [
                'offer_id' => $offer->id,
                'rate_key_present' => filled($offer->rate_key),
            ], [], [
                'status' => 'failed',
                'session_id' => $offer->search->session_id,
                'offer_id' => $offer->id,
                'duration_ms' => $this->durationMs($startedAt),
                'error_message' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        if ($applicationError = $this->applicationError($response, 'HotelPriceCheckRS')) {
            $this->travelLogger->record('hotel', 'price_check', $offer->provider, [
                'offer_id' => $offer->id,
                'rate_key_present' => true,
            ], [
                'application_status' => data_get($response, 'HotelPriceCheckRS.ApplicationResults.status'),
            ], [
                'status' => 'failed',
                'session_id' => $offer->search->session_id,
                'offer_id' => $offer->id,
                'duration_ms' => $this->durationMs($startedAt),
                'error_message' => $applicationError,
            ]);
            throw new RuntimeException('The hotel supplier could not validate this rate: '.$applicationError);
        }

        $bookingKey = $this->bookingKey($response);
        $priceChanged = $this->priceChanged($response);

        if ($bookingKey === '') {
            $message = 'The hotel price check did not return the BookingKey required for supplier confirmation.';
            $this->travelLogger->record('hotel', 'price_check', $offer->provider, [
                'offer_id' => $offer->id,
                'rate_key_present' => true,
            ], [
                'booking_key_received' => false,
                'price_changed' => $priceChanged,
            ], [
                'status' => 'failed',
                'session_id' => $offer->search->session_id,
                'offer_id' => $offer->id,
                'duration_ms' => $this->durationMs($startedAt),
                'error_message' => $message,
            ]);
            throw new RuntimeException($message);
        }

        if ($priceChanged) {
            $message = 'The hotel changed the live rate during price check. Refresh the hotel results before booking.';
            $this->travelLogger->record('hotel', 'price_check', $offer->provider, [
                'offer_id' => $offer->id,
                'rate_key_present' => true,
            ], [
                'booking_key_received' => true,
                'price_changed' => true,
                'price_difference' => data_get($response, 'HotelPriceCheckRS.PriceCheckInfo.PriceDifference'),
                'currency' => data_get($response, 'HotelPriceCheckRS.PriceCheckInfo.CurrencyCode'),
            ], [
                'status' => 'failed',
                'session_id' => $offer->search->session_id,
                'offer_id' => $offer->id,
                'duration_ms' => $this->durationMs($startedAt),
                'error_message' => $message,
            ]);
            throw new RuntimeException($message);
        }

        try {
            $this->requestBuilder->assertBookable($response);
        } catch (\Throwable $exception) {
            $this->travelLogger->record('hotel', 'price_check', $offer->provider, [
                'offer_id' => $offer->id,
                'rate_key_present' => true,
            ], [
                'booking_key_received' => true,
                'price_changed' => false,
            ], [
                'status' => 'failed',
                'session_id' => $offer->search->session_id,
                'offer_id' => $offer->id,
                'duration_ms' => $this->durationMs($startedAt),
                'error_message' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        $this->travelLogger->record('hotel', 'price_check', $offer->provider, [
            'offer_id' => $offer->id,
            'rate_key_present' => true,
        ], [
            'booking_key_received' => true,
            'price_changed' => false,
            'currency' => data_get($response, 'HotelPriceCheckRS.PriceCheckInfo.CurrencyCode'),
        ], [
            'session_id' => $offer->search->session_id,
            'offer_id' => $offer->id,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $response;
    }

    /** @param array<string, mixed> $response */
    private function bookingKey(array $response): string
    {
        foreach ([
            'HotelPriceCheckRS.PriceCheckInfo.BookingKey',
            'PriceCheckInfo.BookingKey',
            'BookingKey',
        ] as $path) {
            $value = data_get($response, $path);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /** @param array<string, mixed> $response */
    private function priceChanged(array $response): bool
    {
        $value = data_get($response, 'HotelPriceCheckRS.PriceCheckInfo.PriceChange', data_get($response, 'PriceCheckInfo.PriceChange', false));

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }

    /** @param array<string, mixed> $response */
    private function locator(array $response): string
    {
        foreach ([
            'CreatePassengerNameRecordRS.ItineraryRef.ID',
            'ItineraryRef.ID',
            'reservationId',
            'confirmationId',
            'pnr',
        ] as $path) {
            $value = data_get($response, $path);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /** @param array<string, mixed> $response */
    private function hotelConfirmation(array $response): ?string
    {
        $found = $this->firstNestedScalarByKeys($response, [
            'HotelConfirmationNumber',
            'ConfirmationNumber',
            'ConfirmationCode',
            'ConfirmationId',
        ]);

        return is_string($found) && trim($found) !== '' ? trim($found) : null;
    }

    /** @param array<string, mixed> $response */
    private function applicationError(array $response, string $root): ?string
    {
        $results = data_get($response, $root.'.ApplicationResults');
        if (! is_array($results)) {
            return null;
        }

        $status = strtoupper(trim((string) ($results['status'] ?? '')));
        $errors = $results['Error'] ?? $results['Errors'] ?? [];
        $messages = [];
        $this->collectErrorMessages($errors, $messages);
        $messages = array_values(array_unique(array_filter(array_map('trim', $messages))));

        if ($messages !== []) {
            return implode(' — ', array_slice($messages, 0, 4));
        }

        if ($status !== '' && ! in_array($status, ['COMPLETE', 'SUCCESS'], true)) {
            return 'Supplier application status: '.$status;
        }

        return null;
    }

    private function collectErrorMessages(mixed $data, array &$messages): void
    {
        if (is_string($data)) {
            if (trim($data) !== '') {
                $messages[] = trim($data);
            }
            return;
        }

        if (! is_array($data)) {
            return;
        }

        foreach ($data as $key => $value) {
            if (is_string($value) && in_array(strtolower((string) $key), ['content', 'message', 'shorttext', 'errormessage'], true)) {
                if (trim($value) !== '') {
                    $messages[] = trim($value);
                }
                continue;
            }

            if (is_array($value)) {
                $this->collectErrorMessages($value, $messages);
            }
        }
    }

    private function firstNestedScalarByKeys(mixed $data, array $keys): mixed
    {
        if (! is_array($data)) {
            return null;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && is_scalar($data[$key])) {
                return $data[$key];
            }
        }

        foreach ($data as $value) {
            $found = $this->firstNestedScalarByKeys($value, $keys);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $response @return array<string, mixed> */
    private function priceCheckSummary(array $response): array
    {
        if ($response === []) {
            return [];
        }

        return array_filter([
            'price_changed' => $this->priceChanged($response),
            'price_difference' => data_get($response, 'HotelPriceCheckRS.PriceCheckInfo.PriceDifference'),
            'currency' => data_get($response, 'HotelPriceCheckRS.PriceCheckInfo.CurrencyCode'),
            'booking_key_received' => $this->bookingKey($response) !== '',
        ], fn (mixed $value): bool => $value !== null);
    }

    /** @param array<string, mixed> $response @return array<string, mixed> */
    private function providerResponseSummary(array $response): array
    {
        return array_filter([
            'application_status' => data_get($response, 'CreatePassengerNameRecordRS.ApplicationResults.status'),
            'provider_locator' => $this->locator($response),
            'hotel_confirmation' => $this->hotelConfirmation($response),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function source(): string
    {
        if (auth()->user()?->isAdmin()) return 'admin';
        if (auth()->user()?->isB2b()) return 'b2b_portal';
        if (request()->header('X-Client-Platform') === 'mobile') return 'mobile_app';

        return 'website';
    }
}

<?php

namespace App\Travel;

use App\Mail\BookingConfirmation;
use App\Mail\PaymentReceipt;
use App\Models\Addon;
use App\Models\AnalyticsEvent;
use App\Models\Customer;
use App\Models\HotelOffer;
use App\Models\Order;
use App\Models\User;
use App\Support\TravelLogger;
use App\Travel\Exceptions\BookingCreationException;
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
        private readonly ExchangeRateService $rates,
        private readonly OperatorMarkupCalculator $operatorMarkup,
        private readonly TravelLogger $travelLogger,
        private readonly TravelApiClient $client,
        private readonly TravelApiHotelBookingRequestBuilder $bookingBuilder,
    ) {}

    /**
     * Validate the supplier rate before a payment attempt is opened.
     *
     * Hotel payment and supplier confirmation are separate operations. This
     * preflight prevents collecting money when the current rate cannot produce
     * the BookingKey or agency guarantee required for reservation creation.
     */
    public function assertCanCreate(HotelOffer $offer): void
    {
        $offer->loadMissing('search');
        if ($offer->expires_at->isPast()) {
            throw new RuntimeException('This hotel rate has expired. Search again.');
        }

        if ($offer->provider === 'fake') {
            return;
        }

        $response = $this->client->checkHotelPrice($this->bookingBuilder->priceCheck($offer));
        $this->bookingBuilder->assertBookable($response);

        if ($this->bookingBuilder->bookingKey($response) === '') {
            throw new RuntimeException('The hotel price check did not return a booking key. Search again before booking.');
        }
    }

    /** @param array<string, mixed> $bookingContact */
    public function create(HotelOffer $offer, Customer $customer, Collection|array $addons = [], array $manualMarkup = [], ?string $specialRequests = null, bool $sendConfirmation = true, array $bookingContact = [], ?int $ownerUserId = null): Order
    {
        $offer->loadMissing('search');
        if ($offer->expires_at->isPast()) {
            throw new BookingCreationException(
                'availability',
                'The hotel rate expired before the reservation could be completed. Your payment is safe and Karossy is reviewing the booking.',
                'This hotel rate expired before supplier booking.',
            );
        }

        $bookingContact = $this->bookingContact($customer, $bookingContact);
        $providerCustomer = new Customer([
            'first_name' => $bookingContact['first_name'],
            'last_name' => $bookingContact['last_name'],
            'email' => $bookingContact['email'],
            'phone' => $bookingContact['phone'],
        ]);

        $addons = collect($addons)->filter(fn ($addon) => $addon instanceof Addon && $addon->active && $addon->type === 'hotel');
        $addonRows = $addons->mapWithKeys(function (Addon $addon) use ($offer): array {
            $converted = $this->rates->convertMinor($addon->price_cents, $addon->currency, $offer->currency);
            return [$addon->id => ['id' => (string) Str::uuid(), 'quantity' => 1, 'price_cents' => $converted['amount_minor'], 'currency' => $offer->currency]];
        });
        $addonTotal = (int) $addonRows->sum('price_cents');
        $operatorMarkup = $this->operatorMarkup->calculate($offer->selling_total_minor + $addonTotal, $manualMarkup['type'] ?? null, $manualMarkup['value'] ?? null);
        $providerResponse = [];
        if ($offer->provider === 'fake') {
            $locator = 'HTL-'.Str::upper(Str::random(7));
        } else {
            try {
                $priceCheckResponse = $this->client->checkHotelPrice($this->bookingBuilder->priceCheck($offer));
                $this->bookingBuilder->assertBookable($priceCheckResponse);
                $bookingKey = $this->bookingBuilder->bookingKey($priceCheckResponse);
                if ($bookingKey === '') {
                    throw new RuntimeException('The hotel price check did not return a booking key. Search again before booking.');
                }
                $providerResponse = $this->client->createHotelBooking(
                    $this->bookingBuilder->booking($offer, $providerCustomer, $bookingKey, $priceCheckResponse, $specialRequests),
                );
                $locator = $this->bookingBuilder->locator($providerResponse);
                if ($locator === '') {
                    throw new RuntimeException('The hotel reservation response did not contain a confirmation locator.');
                }
            } catch (\Throwable $exception) {
                $this->travelLogger->record('hotel', 'booking', $offer->provider, [
                    'offer_id' => $offer->id,
                    'customer_id' => $customer->exists ? $customer->id : null,
                ], $providerResponse, [
                    'status' => 'failed',
                    'session_id' => $offer->search->session_id,
                    'offer_id' => $offer->id,
                    'error_message' => $exception->getMessage(),
                ]);
                throw new BookingCreationException(
                    'supplier',
                    'The hotel supplier could not confirm the reservation. Your payment is confirmed and Karossy is reviewing the supplier response.',
                    $exception->getMessage(),
                    previous: $exception,
                );
            }
        }
        $status = 'confirmed';

        try {
            $order = DB::transaction(function () use ($offer, $customer, $addonRows, $addonTotal, $operatorMarkup, $status, $locator, $specialRequests, $providerResponse, $bookingContact, $ownerUserId): Order {
                $order = Order::create([
                    'reference' => 'KAR-H-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                    'user_id' => $ownerUserId ?? auth()->id(),
                    'customer_id' => $customer->exists ? $customer->id : null,
                    'channel' => auth()->user()?->isB2b() ? 'b2b' : (auth()->user()?->isAdmin() ? 'admin' : 'consumer'),
                    'status' => $status,
                    'currency' => $offer->currency,
                    'subtotal_minor' => $offer->selling_total_minor,
                    'fees_minor' => $addonTotal + $operatorMarkup['amount_minor'],
                    'operator_markup_type' => $operatorMarkup['type'],
                    'operator_markup_value' => $operatorMarkup['value'],
                    'operator_markup_minor' => $operatorMarkup['amount_minor'],
                    'total_minor' => $offer->selling_total_minor + $addonTotal + $operatorMarkup['amount_minor'],
                    'customer' => [
                        'name' => $bookingContact['name'],
                        'email' => $bookingContact['email'],
                        'phone' => $bookingContact['phone'],
                    ],
                    'expires_at' => $offer->expires_at,
                ]);
                $booking = $order->bookings()->create([
                    'product_type' => 'hotel',
                    'provider' => $offer->provider,
                    'provider_locator' => $locator,
                    'status' => $status,
                    'source' => $this->source(),
                    'referrer' => request()->headers->get('referer'),
                    'travellers' => [['name' => $bookingContact['name'], 'email' => $bookingContact['email']]],
                    'details' => [
                        'hotel_offer_id' => $offer->id,
                        'stay' => ['hotel_name' => $offer->name, 'hotel_code' => $offer->hotel_code, 'check_in' => $offer->search->check_in->toDateString(), 'check_out' => $offer->search->check_out->toDateString(), 'rooms' => $offer->search->rooms, 'adults' => $offer->search->adults, 'children' => $offer->search->children, 'room_name' => $offer->room_name, 'rate_name' => $offer->rate_name],
                        'special_requests' => $specialRequests,
                        'pricing' => ['base_minor' => $offer->provider_total_minor, 'configured_markup_minor' => $offer->markup_minor, 'addons_minor' => $addonTotal, 'operator_markup_minor' => $operatorMarkup['amount_minor']],
                        'provider_confirmation_required' => $offer->provider !== 'fake',
                        'provider_response' => $providerResponse,
                    ],
                    'booked_at' => now(),
                ]);
                if ($addonRows->isNotEmpty()) {
                    $booking->addons()->attach($addonRows->all());
                }

                return $order;
            })->load('bookings.addons');
        } catch (\Throwable $exception) {
            $this->travelLogger->record('hotel', 'booking_persistence', 'karossy', [
                'offer_id' => $offer->id,
                'provider_locator' => $locator,
            ], [], [
                'status' => 'failed',
                'session_id' => $offer->search->session_id,
                'offer_id' => $offer->id,
                'error_message' => $exception->getMessage(),
            ]);

            throw new BookingCreationException(
                'persistence',
                'The hotel supplier returned a confirmation locator, but Karossy could not finish saving the booking. Do not pay again; support is reconciling it now.',
                $exception->getMessage(),
                $locator,
                $exception,
            );
        }

        $booking = $order->bookings->first();
        AnalyticsEvent::create(['event' => 'hotel_booking_created', 'service' => 'hotels', 'funnel_step' => 'booking_created', 'session_id' => $offer->search->session_id, 'source' => $offer->provider, 'properties' => ['order_id' => $order->id, 'channel' => $order->channel, 'status' => $status], 'occurred_at' => now()]);
        $this->travelLogger->record('hotel', 'booking', $offer->provider, ['offer_id' => $offer->id, 'customer_id' => $customer->exists ? $customer->id : null, 'addon_ids' => $addons->pluck('id')->all()], ['order_id' => $order->id, 'status' => $status, 'provider_locator' => $locator], ['session_id' => $offer->search->session_id, 'order_id' => $order->id]);
        if ($sendConfirmation) $this->sendConfirmation($order);
        return $order;
    }

    public function sendConfirmation(Order $order): void
    {
        $order->loadMissing(['bookings.addons']);
        $booking = $order->bookings->first();
        $email = data_get($order->customer, 'email');
        if (! $booking || ! filter_var($email, FILTER_VALIDATE_EMAIL)) return;
        try { Mail::to($email)->send(new BookingConfirmation($order, $booking)); }
        catch (\Throwable $exception) { Log::error('Failed to send hotel booking email.', ['order_id' => $order->id, 'error' => $exception->getMessage()]); }
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
        } catch (\Throwable $exception) {
            Log::error('Failed to send hotel payment receipt email.', [
                'order_id' => $order->id,
                'email' => $receiptEmail,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /** @param array<string, mixed> $contact @return array{name:string,first_name:string,last_name:string,email:string,phone:?string} */
    private function bookingContact(Customer $customer, array $contact): array
    {
        $firstName = trim((string) ($contact['first_name'] ?? $customer->first_name));
        $lastName = trim((string) ($contact['last_name'] ?? $customer->last_name));
        $name = trim((string) ($contact['name'] ?? trim($firstName.' '.$lastName)));

        return [
            'name' => $name !== '' ? $name : ($customer->full_name ?: 'Valued Traveller'),
            'first_name' => $firstName !== '' ? $firstName : ($customer->first_name ?: 'Guest'),
            'last_name' => $lastName !== '' ? $lastName : ($customer->last_name ?: 'Traveller'),
            'email' => strtolower(trim((string) ($contact['email'] ?? $customer->email))),
            'phone' => isset($contact['phone']) ? trim((string) $contact['phone']) : $customer->phone,
        ];
    }

    private function source(): string
    {
        if (auth()->user()?->isAdmin()) return 'admin';
        if (auth()->user()?->isB2b()) return 'b2b_portal';
        if (request()->header('X-Client-Platform') === 'mobile') return 'mobile_app';
        return 'website';
    }
}

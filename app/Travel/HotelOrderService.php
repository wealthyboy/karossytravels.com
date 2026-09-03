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
        private readonly ExchangeRateService $rates,
        private readonly OperatorMarkupCalculator $operatorMarkup,
        private readonly TravelLogger $travelLogger,
        private readonly TravelApiClient $client,
        private readonly TravelApiHotelBookingRequestBuilder $bookingBuilder,
    ) {}

    public function create(HotelOffer $offer, Customer $customer, Collection|array $addons = [], array $manualMarkup = [], ?string $specialRequests = null, bool $sendConfirmation = true): Order
    {
        $offer->loadMissing('search');
        if ($offer->expires_at->isPast()) throw new RuntimeException('This hotel rate has expired. Search again.');

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
                    $this->bookingBuilder->booking($offer, $customer, $bookingKey, $priceCheckResponse, $specialRequests),
                );
                $locator = $this->bookingBuilder->locator($providerResponse);
                if ($locator === '') {
                    throw new RuntimeException('The hotel reservation response did not contain a confirmation locator.');
                }
            } catch (\Throwable $exception) {
                $this->travelLogger->record('hotel', 'booking', $offer->provider, [
                    'offer_id' => $offer->id,
                    'customer_id' => $customer->id,
                ], [], [
                    'status' => 'failed',
                    'session_id' => $offer->search->session_id,
                    'offer_id' => $offer->id,
                    'error_message' => $exception->getMessage(),
                ]);
                throw $exception;
            }
        }
        $status = 'confirmed';

        $order = DB::transaction(function () use ($offer, $customer, $addonRows, $addonTotal, $operatorMarkup, $status, $locator, $specialRequests, $providerResponse): Order {
            $order = Order::create([
                'reference' => 'KAR-H-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'user_id' => auth()->id(), 'customer_id' => $customer->id,
                'channel' => auth()->user()?->isB2b() ? 'b2b' : (auth()->user()?->isAdmin() ? 'admin' : 'consumer'),
                'status' => $status, 'currency' => $offer->currency,
                'subtotal_minor' => $offer->selling_total_minor,
                'fees_minor' => $addonTotal + $operatorMarkup['amount_minor'],
                'operator_markup_type' => $operatorMarkup['type'], 'operator_markup_value' => $operatorMarkup['value'],
                'operator_markup_minor' => $operatorMarkup['amount_minor'],
                'total_minor' => $offer->selling_total_minor + $addonTotal + $operatorMarkup['amount_minor'],
                'customer' => ['name' => $customer->full_name, 'email' => $customer->email, 'phone' => $customer->phone],
                'expires_at' => $offer->expires_at,
            ]);
            $booking = $order->bookings()->create([
                'product_type' => 'hotel', 'provider' => $offer->provider, 'provider_locator' => $locator,
                'status' => $status, 'source' => $this->source(), 'referrer' => request()->headers->get('referer'),
                'travellers' => [['name' => $customer->full_name, 'email' => $customer->email]],
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
            if ($addonRows->isNotEmpty()) $booking->addons()->attach($addonRows->all());
            return $order;
        })->load('bookings.addons');

        $booking = $order->bookings->first();
        AnalyticsEvent::create(['event' => 'hotel_booking_created', 'service' => 'hotels', 'funnel_step' => 'booking_created', 'session_id' => $offer->search->session_id, 'source' => $offer->provider, 'properties' => ['order_id' => $order->id, 'channel' => $order->channel, 'status' => $status], 'occurred_at' => now()]);
        $this->travelLogger->record('hotel', 'booking', $offer->provider, ['offer_id' => $offer->id, 'customer_id' => $customer->id, 'addon_ids' => $addons->pluck('id')->all()], ['order_id' => $order->id, 'status' => $status, 'provider_locator' => $locator], ['session_id' => $offer->search->session_id, 'order_id' => $order->id]);
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

    private function source(): string
    {
        if (auth()->user()?->isAdmin()) return 'admin';
        if (auth()->user()?->isB2b()) return 'b2b_portal';
        if (request()->header('X-Client-Platform') === 'mobile') return 'mobile_app';
        return 'website';
    }
}

<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\HotelOffer;
use App\Models\HotelSearch;
use App\Travel\HotelOrderService;
use App\Travel\TravelApi\TravelApiClient;
use App\Travel\TravelApi\TravelApiHotelBookingRequestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HotelProviderBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_rejects_a_gds_rate_without_iata_before_payment(): void
    {
        config([
            'services.travel.travel_api.auth_scheme' => 'bearer_token',
            'services.travel.travel_api.access_token' => 'test-token',
            'services.travel.travel_api.environment' => 'cert',
            'services.travel.travel_api.cert_url' => 'https://travel.test',
            'services.travel.travel_api.hotel_price_check_path' => '/v4.0.0/hotel/pricecheck',
            'services.travel.travel_api.pcc' => 'TEST',
            'services.travel.travel_api.iata_number' => null,
        ]);
        app()->forgetInstance(TravelApiClient::class);
        app()->forgetInstance(TravelApiHotelBookingRequestBuilder::class);

        Http::preventStrayRequests();
        Http::fake([
            'https://travel.test/v4.0.0/hotel/pricecheck' => Http::response([
                'HotelPriceCheckRS' => ['PriceCheckInfo' => [
                    'BookingKey' => 'BOOK-123',
                    'HotelRateInfo' => ['RateSource' => '100'],
                ]],
            ]),
        ]);

        [$offer] = $this->offerAndCustomer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('IATA RequestorID');

        app(HotelOrderService::class)->assertCanCreate($offer);
    }

    public function test_real_hotel_offer_is_confirmed_only_after_provider_returns_a_locator(): void
    {
        config([
            'services.travel.travel_api.auth_scheme' => 'bearer_token',
            'services.travel.travel_api.access_token' => 'test-token',
            'services.travel.travel_api.environment' => 'cert',
            'services.travel.travel_api.cert_url' => 'https://travel.test',
            'services.travel.travel_api.hotel_price_check_path' => '/v4.0.0/hotel/pricecheck',
            'services.travel.travel_api.hotel_booking_path' => '/v2.5.0/passenger/records?mode=create',
            'services.travel.travel_api.pcc' => 'TEST',
            'services.travel.travel_api.iata_number' => null,
        ]);
        app()->forgetInstance(TravelApiClient::class);
        app()->forgetInstance(TravelApiHotelBookingRequestBuilder::class);

        Http::preventStrayRequests();
        Http::fake([
            'https://travel.test/v4.0.0/hotel/pricecheck' => Http::response([
                'HotelPriceCheckRS' => ['PriceCheckInfo' => [
                    'BookingKey' => 'BOOK-123',
                    'RateSource' => '110',
                ]],
            ]),
            'https://travel.test/v2.5.0/passenger/records?mode=create' => Http::response([
                'CreatePassengerNameRecordRS' => ['ItineraryRef' => ['ID' => 'HTL123']],
            ]),
        ]);

        [$offer, $customer] = $this->offerAndCustomer();

        $order = app(HotelOrderService::class)->create($offer, $customer, sendConfirmation: false);

        $this->assertSame('confirmed', $order->status);
        $this->assertDatabaseHas('bookings', [
            'order_id' => $order->id,
            'product_type' => 'hotel',
            'status' => 'confirmed',
            'provider_locator' => 'HTL123',
        ]);
        Http::assertSentCount(2);
    }

    /** @return array{HotelOffer, Customer} */
    private function offerAndCustomer(): array
    {
        $search = HotelSearch::create([
            'session_id' => (string) Str::uuid(),
            'channel' => 'consumer',
            'provider' => 'travel_api',
            'destination_code' => 'LOS',
            'destination_label' => 'Lagos',
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(12),
            'adults' => 1,
            'children' => 0,
            'rooms' => 1,
            'currency' => 'USD',
            'result_count' => 1,
        ]);
        $offer = HotelOffer::create([
            'hotel_search_id' => $search->id,
            'provider' => 'travel_api',
            'hotel_code' => 'HOTEL-1',
            'name' => 'Provider Hotel',
            'room_name' => 'Studio',
            'rate_name' => 'Regular Rate',
            'rate_key' => 'RATE-123',
            'currency' => 'USD',
            'provider_total_minor' => 20000,
            'markup_minor' => 0,
            'selling_total_minor' => 20000,
            'nightly_minor' => 10000,
            'expires_at' => now()->addMinutes(15),
        ]);
        $customer = Customer::create([
            'first_name' => 'Ada',
            'last_name' => 'Okafor',
            'email' => 'ada@example.test',
            'phone' => '+2348000000000',
            'status' => 'active',
        ]);

        return [$offer, $customer];
    }
}

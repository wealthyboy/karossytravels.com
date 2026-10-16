<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\HotelOffer;
use App\Models\HotelSearch;
use App\Models\TravelLog;
use App\Travel\Exceptions\BookingCreationException;
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
                'CreatePassengerNameRecordRS' => [
                    'ApplicationResults' => ['status' => 'Complete'],
                    'ItineraryRef' => ['ID' => 'HTL123'],
                ],
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
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/passenger/records')
            && data_get($request->data(), 'CreatePassengerNameRecordRQ.HotelBook.Rooms.Room.0.RoomIndex') === 1);
    }

    public function test_incomplete_sabre_booking_preserves_the_room_index_error_in_operational_logs(): void
    {
        $this->assertIncompleteBookingIsRejected();
    }

    public function test_incomplete_sabre_booking_is_not_confirmed_even_if_a_locator_is_present(): void
    {
        $this->assertIncompleteBookingIsRejected('PNR123');
    }

    private function assertIncompleteBookingIsRejected(?string $locator = null): void
    {
        config([
            'services.travel.travel_api.auth_scheme' => 'bearer_token',
            'services.travel.travel_api.access_token' => 'test-token',
            'services.travel.travel_api.environment' => 'cert',
            'services.travel.travel_api.cert_url' => 'https://travel.test',
            'services.travel.travel_api.hotel_price_check_path' => '/v4.0.0/hotel/pricecheck',
            'services.travel.travel_api.hotel_booking_path' => '/v2.5.0/passenger/records?mode=create',
            'services.travel.travel_api.pcc' => 'TEST',
            'services.travel.travel_api.iata_number' => '12345678',
        ]);
        app()->forgetInstance(TravelApiClient::class);
        app()->forgetInstance(TravelApiHotelBookingRequestBuilder::class);

        $response = ['CreatePassengerNameRecordRS' => [
            'ApplicationResults' => [
                'status' => 'Incomplete',
                'Error' => [[
                    'SystemSpecificResults' => [[
                        'Message' => [[
                            'code' => 'ERR.SP.BUSINESS_ERROR',
                            'content' => 'PNR has not been created successfully, see remaining messages for details',
                        ]],
                    ]],
                ]],
                'Warning' => [[
                    'SystemSpecificResults' => [[
                        'Message' => [[
                            'code' => 'WARN.SP.PROVIDER_ERROR',
                            'content' => 'UpdateReservationRQ: NGHP.ERR.0767: Invalid room stay index. (Code: 1)',
                        ]],
                    ]],
                ]],
            ],
        ]];
        if ($locator !== null) {
            $response['CreatePassengerNameRecordRS']['ItineraryRef'] = ['ID' => $locator];
        }

        Http::preventStrayRequests();
        Http::fake([
            'https://travel.test/v4.0.0/hotel/pricecheck' => Http::response([
                'HotelPriceCheckRS' => ['PriceCheckInfo' => [
                    'BookingKey' => 'BOOK-123',
                    'HotelRateInfo' => ['RateSource' => '100'],
                ]],
            ]),
            'https://travel.test/v2.5.0/passenger/records?mode=create' => Http::response($response),
        ]);
        [$offer, $customer] = $this->offerAndCustomer();

        try {
            app(HotelOrderService::class)->create($offer, $customer, sendConfirmation: false);
            $this->fail('An incomplete Sabre hotel reservation must not be confirmed.');
        } catch (BookingCreationException $exception) {
            $this->assertSame('supplier', $exception->stage);
            $this->assertStringContainsString('NGHP.ERR.0767', $exception->getMessage());
            $this->assertStringContainsString('Invalid room stay index', $exception->getMessage());
            $this->assertStringNotContainsString('NGHP.ERR.0767', $exception->publicMessage);
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('bookings', 0);
        $log = TravelLog::query()->where('stage', 'booking')->firstOrFail();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('NGHP.ERR.0767', $log->error_message);
        $this->assertSame($response, $log->response_payload);
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

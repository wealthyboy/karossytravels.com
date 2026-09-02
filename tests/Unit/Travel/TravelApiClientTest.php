<?php

namespace Tests\Unit\Travel;

use App\Travel\TravelApi\TravelApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use RuntimeException;

final class TravelApiClientTest extends TestCase
{
    public function test_it_fetches_and_caches_a_v2_epr_token_without_client_credentials(): void
    {
        Cache::flush();
        Http::fake([
            'https://travel-api.test/v2/auth/token' => Http::response([
                'access_token' => 'epr-test-token',
                'expires_in' => 604800,
            ]),
        ]);

        $client = new TravelApiClient([
            'environment' => 'cert', 'auth_scheme' => 'epr_v2',
            'cert_url' => 'https://travel-api.test',
            'production_url' => 'https://travel-api.test',
            'user_id' => '847195', 'password' => 'test-password',
            'pcc' => 'WD4H', 'domain' => 'AA', 'timeout' => 30,
            'token_path' => '/v2/auth/token',
        ]);

        $this->assertSame('epr-test-token', $client->accessToken());
        $this->assertTrue($client->status()['token_cached']);
        Http::assertSent(function ($request): bool {
            $expected = base64_encode(base64_encode('V1:847195:WD4H:AA').':'.base64_encode('test-password'));

            return $request->url() === 'https://travel-api.test/v2/auth/token'
                && $request['grant_type'] === 'client_credentials'
                && ($request->header('Authorization')[0] ?? null) === 'Basic '.$expected;
        });
    }

    public function test_it_fetches_and_caches_a_password_grant_token(): void
    {
        Cache::flush();
        Http::fake([
            'https://travel-api.test/v3/auth/token' => Http::response([
                'access_token' => 'temporary-test-token',
                'expires_in' => 900,
            ]),
        ]);

        $client = new TravelApiClient([
            'environment' => 'cert',
            'auth_scheme' => 'password_grant',
            'cert_url' => 'https://travel-api.test',
            'production_url' => 'https://travel-api.test',
            'user_id' => 'test-user',
            'password' => 'test-password',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'pcc' => 'TEST',
            'domain' => 'DEFAULT',
            'timeout' => 30,
            'token_path' => '/v3/auth/token',
        ]);

        $this->assertSame('temporary-test-token', $client->accessToken());
        $this->assertSame('temporary-test-token', $client->accessToken());
        $this->assertTrue($client->status()['token_cached']);
        $this->assertNotNull($client->status()['token_expires_at']);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->isForm()
            && str_starts_with($request->header('Authorization')[0] ?? '', 'Basic ')
            && $request['grant_type'] === 'password'
            && $request['username'] === 'test-user-TEST-AA'
            && $request['password'] === 'test-password');
    }

    public function test_it_retries_transient_token_failures_before_succeeding(): void
    {
        Cache::flush();
        Http::fake([
            'https://travel-api.test/v2/auth/token' => Http::sequence()
                ->push(['message' => 'temporarily unavailable'], 500)
                ->push(['message' => 'temporarily unavailable'], 503)
                ->push(['access_token' => 'recovered-token', 'expires_in' => 900], 200),
        ]);

        $client = new TravelApiClient([
            'environment' => 'cert', 'auth_scheme' => 'epr_v2',
            'cert_url' => 'https://travel-api.test',
            'production_url' => 'https://travel-api.test',
            'user_id' => '847195', 'password' => 'test-password',
            'pcc' => 'WD4H', 'domain' => 'AA', 'timeout' => 10,
            'token_timeout' => 30, 'token_connect_timeout' => 10,
            'token_attempts' => 3, 'token_retry_delay_ms' => 0,
            'token_path' => '/v2/auth/token',
        ]);

        $this->assertSame('recovered-token', $client->accessToken());
        Http::assertSentCount(3);
    }

    public function test_it_can_force_refresh_the_cached_token(): void
    {
        Cache::flush();
        Http::fake([
            'https://travel-api.test/v3/auth/token' => Http::sequence()
                ->push(['access_token' => 'first-token', 'expires_in' => 900])
                ->push(['access_token' => 'second-token', 'expires_in' => 900]),
        ]);

        $client = new TravelApiClient([
            'environment' => 'cert', 'auth_scheme' => 'password_grant',
            'cert_url' => 'https://travel-api.test',
            'production_url' => 'https://travel-api.test',
            'user_id' => 'test-user', 'password' => 'test-password',
            'client_id' => 'test-client-id', 'client_secret' => 'test-client-secret',
            'pcc' => 'TEST', 'domain' => 'DEFAULT', 'timeout' => 30,
            'token_path' => '/v3/auth/token',
        ]);

        $this->assertSame('first-token', $client->accessToken());
        $this->assertTrue($client->authenticate(force: true)['ready']);
        $this->assertSame('second-token', $client->accessToken());
        Http::assertSentCount(2);
    }

    public function test_it_uses_the_configured_air_workflow_endpoints(): void
    {
        Http::fake(['https://travel-api.test/*' => Http::response(['ok' => true])]);

        $client = new TravelApiClient([
            'environment' => 'cert',
            'auth_scheme' => 'bearer_token',
            'access_token' => 'test-token',
            'cert_url' => 'https://travel-api.test',
            'production_url' => 'https://travel-api.test',
            'timeout' => 30,
            'flight_shop_path' => '/v5/offers/shop',
            'flight_revalidate_path' => '/v5/shop/flights/revalidate',
            'order_create_path' => '/v1/trip/orders/create',
        ]);

        $client->shopFlights(['request' => 'shop']);
        $client->revalidateFlightOffer(['request' => 'revalidate']);
        $client->createTripOrder(['request' => 'create']);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://travel-api.test/v5/offers/shop');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://travel-api.test/v5/shop/flights/revalidate');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://travel-api.test/v1/trip/orders/create');
    }

    public function test_hotel_price_check_maps_the_legacy_path_and_sends_sabre_application_headers(): void
    {
        Http::fake([
            'https://travel-api.test/v2.1.0/hotel/pricecheck' => Http::response([
                'HotelPriceCheckRS' => [
                    'PriceCheckInfo' => ['BookingKey' => 'BOOK-123'],
                ],
            ]),
        ]);

        $client = new TravelApiClient([
            'environment' => 'cert',
            'auth_scheme' => 'bearer_token',
            'access_token' => 'test-token',
            'cert_url' => 'https://travel-api.test',
            'production_url' => 'https://travel-api.test',
            'timeout' => 30,
            'application_id' => 'KAROSSY-APP',
            'hotel_price_check_path' => '/v5/hotelpricecheck',
        ]);

        $response = $client->priceCheckHotel([
            'HotelPriceCheckRQ' => [
                'version' => '2.1.0',
                'RateInfoRef' => ['RateKey' => 'RATE-123'],
            ],
        ]);

        $this->assertSame('BOOK-123', data_get($response, 'HotelPriceCheckRS.PriceCheckInfo.BookingKey'));
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://travel-api.test/v2.1.0/hotel/pricecheck'
                && ($request->header('Authorization')[0] ?? null) === 'Bearer test-token'
                && ($request->header('Application-ID')[0] ?? null) === 'KAROSSY-APP'
                && filled($request->header('Conversation-ID')[0] ?? null)
                && data_get($request->data(), 'HotelPriceCheckRQ.RateInfoRef.RateKey') === 'RATE-123';
        });
    }

    public function test_it_reports_an_empty_provider_response_without_a_php_type_error(): void
    {
        Http::fake(['https://travel-api.test/*' => Http::response('', 200)]);
        $client = new TravelApiClient([
            'environment' => 'cert', 'auth_scheme' => 'bearer_token', 'access_token' => 'test-token',
            'cert_url' => 'https://travel-api.test', 'production_url' => 'https://travel-api.test',
            'timeout' => 30, 'flight_revalidate_path' => '/v5/shop/flights/revalidate',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The travel system returned no usable data (HTTP 200)');
        $client->revalidateFlightOffer(['request' => 'revalidate']);
    }

    public function test_it_surfaces_a_travel_api_xml_error_returned_with_http_200(): void
    {
        Http::fake(['https://travel-api.test/*' => Http::response(
            '<OTA_AirLowFareSearchRS><Errors><Error Type="SERVER" Code="INVALID" ShortText="Unknown endpoint" /></Errors></OTA_AirLowFareSearchRS>',
            200,
            ['Content-Type' => 'application/json'],
        )]);
        $client = new TravelApiClient([
            'environment' => 'cert', 'auth_scheme' => 'bearer_token', 'access_token' => 'test-token',
            'cert_url' => 'https://travel-api.test', 'production_url' => 'https://travel-api.test',
            'timeout' => 30, 'flight_revalidate_path' => '/v5/shop/flights/revalidate',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The travel system rejected the request: Unknown endpoint');
        $client->revalidateFlightOffer(['request' => 'revalidate']);
    }

    public function test_it_surfaces_a_travel_api_json_error_returned_with_http_400(): void
    {
        Http::fake(['https://travel-api.test/*' => Http::response([
            'status' => 'NotProcessed',
            'errorCode' => 'ERR.SWS.CLIENT.VALIDATION_FAILED',
            'message' => 'Request validation failed',
        ], 400)]);
        $client = new TravelApiClient([
            'environment' => 'cert', 'auth_scheme' => 'bearer_token', 'access_token' => 'test-token',
            'cert_url' => 'https://travel-api.test', 'production_url' => 'https://travel-api.test',
            'timeout' => 30, 'flight_revalidate_path' => '/v5/shop/flights/revalidate',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The travel system rejected the request: Request validation failed — ERR.SWS.CLIENT.VALIDATION_FAILED');
        $client->revalidateFlightOffer(['request' => 'revalidate']);
    }
}

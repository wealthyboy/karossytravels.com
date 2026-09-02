<?php

namespace Tests\Feature\Api;

use App\Models\TravelOffer;
use App\Travel\Contracts\FlightProvider;
use RuntimeException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FlightSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_normalized_offers_and_records_analytics(): void
    {
        $response = $this->postJson('/api/v1/flights/search', [
            'origin' => 'los',
            'destination' => 'abv',
            'departure_date' => now()->addWeek()->toDateString(),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'session_id' => (string) Str::uuid(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.offers.0.segments.0.origin', 'LOS')
            ->assertJsonPath('data.offers.0.price.currency', 'NGN')
            ->assertJsonPath('data.offers.0.price.total_minor', 52500000)
            ->assertJsonPath('data.offers.0.price.markup_minor', 0)
            ->assertJsonPath('meta.api_version', 'v1')
            ->assertHeader('X-Request-ID');

        $this->assertDatabaseHas('analytics_events', [
            'event' => 'flight_search_completed',
            'service' => 'flights',
        ]);
        $this->assertDatabaseCount('flight_searches', 1);
        $this->assertDatabaseCount('travel_offers', 1);
        $this->assertNotEmpty($response->json('meta.search_id'));
        $this->assertNotSame(
            $response->json('data.offers.0.id'),
            TravelOffer::query()->value('provider_reference'),
        );
    }

    public function test_it_accepts_a_multi_city_itinerary(): void
    {
        $firstDate = now()->addWeek()->toDateString();
        $secondDate = now()->addWeeks(2)->toDateString();

        $response = $this->postJson('/api/v1/flights/search', [
            'trip_type' => 'multi_city',
            'segments' => [
                ['origin' => 'los', 'destination' => 'lhr', 'departure_date' => $firstDate],
                ['origin' => 'lhr', 'destination' => 'dxb', 'departure_date' => $secondDate],
            ],
            'cabin' => 'economy',
            'adults' => 1,
            'session_id' => (string) Str::uuid(),
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.offers.0.segments')
            ->assertJsonPath('data.offers.0.segments.0.origin', 'LOS')
            ->assertJsonPath('data.offers.0.segments.1.destination', 'DXB');

        $this->assertDatabaseHas('flight_searches', [
            'trip_type' => 'multi_city',
            'origin' => 'LOS',
            'destination' => 'DXB',
        ]);
        $this->assertSame($firstDate, \App\Models\FlightSearch::query()->firstOrFail()->departure_date->toDateString());
    }

    public function test_it_never_exposes_supplier_or_transport_errors_to_customers(): void
    {
        $this->mock(FlightProvider::class, function ($mock): void {
            $mock->shouldReceive('search')->once()->andReturnUsing(
                fn () => throw new RuntimeException('cURL error 28 for https://private-supplier.example/v5/offers/shop')
            );
            $mock->shouldReceive('name')->andReturn('travel_api');
        });

        $response = $this->postJson('/api/v1/flights/search', [
            'origin' => 'LOS',
            'destination' => 'LHR',
            'departure_date' => now()->addWeek()->toDateString(),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'session_id' => (string) Str::uuid(),
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('message', 'We are having trouble connecting to the airline network. Please check your connection and try again shortly.')
            ->assertJsonMissing(['private-supplier.example'])
            ->assertJsonMissing(['cURL error 28']);
    }
}

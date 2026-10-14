<?php

namespace Tests\Feature;

use App\Travel\Contracts\FlightProvider;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FlightSearchLoadingViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_page_has_an_accessible_indeterminate_loader_without_running_a_supplier_search(): void
    {
        $this->mock(FlightProvider::class)->shouldNotReceive('search');

        $response = $this->get(route('flights.results', $this->criteria()))
            ->assertOk()
            ->assertSee('Finding your best flights')
            ->assertSee('LOS → LHR')
            ->assertSee('Private flights, planned around you.')
            ->assertSee('width="2006" height="784"', false)
            ->assertSee('Please keep this page open.');

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $progress = $xpath->query('//*[@data-public-flight-progress]')->item(0);

        $this->assertNotNull($progress);
        $this->assertSame('progressbar', $progress->getAttribute('role'));
        $this->assertSame('Flight search in progress', $progress->getAttribute('aria-label'));
        $this->assertSame('Searching live airline availability', $progress->getAttribute('aria-valuetext'));
        $this->assertFalse($progress->hasAttribute('aria-valuenow'));

        $this->assertSame(1, $xpath->query('//*[@id="publicFlightSearchModal"]//*[@role="progressbar"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-public-flight-progress]/ancestor::*[contains(concat(" ", normalize-space(@class), " "), " public-flight-search-promo-media ")]')->length);
        $response->assertDontSee('public-flight-search-ambient-progress', false);

        $modal = $xpath->query('//*[@id="publicFlightSearchModal"]')->item(0);
        $this->assertSame('publicFlightSearchModalTitle', $modal->getAttribute('aria-labelledby'));
        $this->assertSame('publicFlightSearchStatus', $modal->getAttribute('aria-describedby'));
        $this->assertSame('status', $xpath->query('//*[@id="publicFlightSearchStatus"]')->item(0)->getAttribute('role'));
        $this->assertDatabaseCount('flight_searches', 0);
        $this->assertDatabaseCount('travel_offers', 0);
    }

    public function test_multi_city_loader_displays_the_first_origin_and_final_destination(): void
    {
        $criteria = $this->criteria();
        unset($criteria['origin'], $criteria['destination'], $criteria['departure_date'], $criteria['return_date']);
        $criteria['trip_type'] = 'multi_city';
        $criteria['segments'] = [
            ['origin' => 'LOS', 'destination' => 'LHR', 'departure_date' => today()->addWeeks(2)->toDateString()],
            ['origin' => 'LHR', 'destination' => 'JFK', 'departure_date' => today()->addWeeks(3)->toDateString()],
        ];

        $this->get(route('flights.results', $criteria))->assertOk()
            ->assertSee('LOS → JFK')
            ->assertSee('Checking live fares from LOS to JFK')
            ->assertDontSee('undefined');
    }

    private function criteria(): array
    {
        return [
            'origin' => 'LOS',
            'destination' => 'LHR',
            'departure_date' => today()->addWeeks(2)->toDateString(),
            'return_date' => today()->addWeeks(3)->toDateString(),
            'trip_type' => 'round_trip',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'session_id' => '0f9405eb-1674-44d2-b6f8-a2a607c26f50',
        ];
    }
}

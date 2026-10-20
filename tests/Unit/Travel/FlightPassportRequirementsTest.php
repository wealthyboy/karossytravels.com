<?php

namespace Tests\Unit\Travel;

use App\Models\TravelOffer;
use App\Travel\FlightPassportRequirements;
use Tests\TestCase;

final class FlightPassportRequirementsTest extends TestCase
{
    public function test_all_segments_must_stay_in_the_same_known_country(): void
    {
        $requirements = new FlightPassportRequirements;
        foreach ([
            [[['origin' => 'LOS', 'destination' => 'ABV']], false],
            [[['origin' => 'los', 'destination' => 'phc'], ['origin' => 'PHC', 'destination' => 'LOS']], false],
            [[['origin' => 'LOS', 'destination' => 'ENU'], ['origin' => 'ENU', 'destination' => 'ABV']], false],
            [[['origin' => 'JFK', 'destination' => 'LAX']], false],
            [[['origin' => 'LOS', 'destination' => 'ACC']], true],
            [[['origin' => 'LOS', 'destination' => 'LHR']], true],
            [[['origin' => 'LOS', 'destination' => 'ACC'], ['origin' => 'ACC', 'destination' => 'ABV']], true],
            [[['origin' => 'LOS', 'destination' => 'ABV'], ['origin' => 'ABV', 'destination' => 'LHR']], true],
            [[['origin' => 'LOS', 'destination' => 'ZZZ']], true],
            [[['origin' => 'LOS', 'destination' => 'ABV', 'stops' => 1]], true],
            [[['origin' => 'LOS']], true],
            [[], true],
        ] as [$itinerary, $expected]) {
            $this->assertSame($expected, $requirements->requiredFor(new TravelOffer(['itinerary' => $itinerary])), json_encode($itinerary));
        }
    }
}

<?php

namespace App\Travel\Providers;

use App\Travel\Contracts\FlightProvider;
use Illuminate\Support\Str;

final class FakeFlightProvider implements FlightProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function search(array $criteria): array
    {
        $departure = $criteria['departure_date'];
        $rawSegments = $criteria['segments'] ?? [[
            'origin' => $criteria['origin'],
            'destination' => $criteria['destination'],
            'departure_date' => $departure,
        ]];

        if (($criteria['trip_type'] ?? null) === 'round_trip' && ! empty($criteria['return_date']) && empty($criteria['segments'])) {
            $rawSegments[] = [
                'origin' => $criteria['destination'],
                'destination' => $criteria['origin'],
                'departure_date' => $criteria['return_date'],
            ];
        }

        $segments = collect($rawSegments)->map(fn (array $segment, int $index): array => [
            'origin' => $segment['origin'],
            'destination' => $segment['destination'],
            'departure_at' => $segment['departure_date'].'T09:00:00+01:00',
            'arrival_at' => $segment['departure_date'].'T11:15:00+01:00',
            'flight_number' => 'KQ'.(101 + $index),
            'cabin' => $criteria['cabin'],
        ])->all();

        return [[
            'id' => (string) Str::uuid(),
            'source' => 'development',
            'validating_airline' => 'KQ',
            'segments' => $segments,
            'price' => [
                'currency' => $criteria['currency'],
                'base_minor' => 45000000,
                'taxes_minor' => 7500000,
                'total_minor' => 52500000,
            ],
            'refundable' => false,
        ]];
    }
}

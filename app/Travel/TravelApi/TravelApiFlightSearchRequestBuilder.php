<?php

namespace App\Travel\TravelApi;

final class TravelApiFlightSearchRequestBuilder
{
    /** @param array<string, mixed> $configuration */
    public function __construct(private readonly array $configuration = []) {}

    /** @param array<string, mixed> $criteria
     *  @return array<string, mixed>
     */
    public function build(array $criteria): array
    {
        $segments = $this->segments($criteria);
        $passengers = [['Code' => 'ADT', 'Quantity' => (int) $criteria['adults']]];

        if (($criteria['children'] ?? 0) > 0) {
            $passengers[] = ['Code' => 'CNN', 'Quantity' => (int) $criteria['children']];
        }
        if (($criteria['infants'] ?? 0) > 0) {
            $passengers[] = ['Code' => 'INF', 'Quantity' => (int) $criteria['infants']];
        }

        $request = [
            'Version' => '5',
            'POS' => ['Source' => [[
                'PseudoCityCode' => (string) $this->configuration['pcc'],
                'RequestorID' => [
                    'Type' => '1',
                    'ID' => (string) ($this->configuration['requestor_id'] ?? '1'),
                    'CompanyName' => ['Code' => (string) ($this->configuration['company_code'] ?? 'TN')],
                ],
            ]]],
            'OriginDestinationInformation' => array_map(fn (array $segment): array => [
                'DepartureDateTime' => $segment['departure_date'].'T00:00:00',
                'OriginLocation' => ['LocationCode' => strtoupper($segment['origin'])],
                'DestinationLocation' => ['LocationCode' => strtoupper($segment['destination'])],
            ], $segments),
            'TravelPreferences' => [
                'CabinPref' => [[
                    'Cabin' => $this->cabinCode((string) $criteria['cabin']),
                    'PreferLevel' => 'Preferred',
                ]],
            ],
            'TravelerInfoSummary' => [
                'AirTravelerAvail' => [['PassengerTypeQuantity' => $passengers]],
            ],
            'TPA_Extensions' => [
                'IntelliSellTransaction' => [
                    'RequestType' => ['Name' => ((int) ($this->configuration['max_itineraries'] ?? 50)).'ITINS'],
                ],
            ],
        ];

        return ['OTA_AirLowFareSearchRQ' => $request];
    }

    /** @param array<string, mixed> $criteria
     *  @return array<int, array{origin:string, destination:string, departure_date:string}>
     */
    private function segments(array $criteria): array
    {
        if ($criteria['trip_type'] === 'multi_city') {
            return $criteria['segments'];
        }

        $segments = [[
            'origin' => $criteria['origin'],
            'destination' => $criteria['destination'],
            'departure_date' => $criteria['departure_date'],
        ]];

        if ($criteria['trip_type'] === 'round_trip') {
            $segments[] = [
                'origin' => $criteria['destination'],
                'destination' => $criteria['origin'],
                'departure_date' => $criteria['return_date'],
            ];
        }

        return $segments;
    }

    private function cabinCode(string $cabin): string
    {
        return match ($cabin) {
            'premium_economy' => 'P',
            'business' => 'C',
            'first' => 'F',
            default => 'Y',
        };
    }
}

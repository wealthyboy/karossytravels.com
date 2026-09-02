<?php

namespace App\Travel\TravelApi;

use App\Models\TravelOffer;
use Carbon\CarbonImmutable;

final class TravelApiFlightRevalidationRequestBuilder
{
    /** @param array<string, mixed> $configuration */
    public function __construct(private readonly array $configuration = []) {}

    /** @return array<string, mixed> */
    public function build(TravelOffer $offer): array
    {
        $search = $offer->flightSearch;
        $journeys = $this->journeys($offer);
        $passengers = [['Code' => 'ADT', 'Quantity' => (int) $search->adults]];

        if ($search->children > 0) {
            $passengers[] = ['Code' => 'CNN', 'Quantity' => (int) $search->children];
        }
        if ($search->infants > 0) {
            $passengers[] = ['Code' => 'INF', 'Quantity' => (int) $search->infants];
        }

        return ['OTA_AirLowFareSearchRQ' => [
            'Version' => '5',
            'POS' => ['Source' => [[
                'PseudoCityCode' => (string) $this->configuration['pcc'],
                'RequestorID' => [
                    'Type' => '1',
                    'ID' => (string) ($this->configuration['requestor_id'] ?? '1'),
                    'CompanyName' => ['Code' => (string) ($this->configuration['company_code'] ?? 'TN')],
                ],
            ]]],
            'OriginDestinationInformation' => $journeys->map(fn (array $journey, int $index): array => [
                'RPH' => (string) ($index + 1),
                'Fixed' => false,
                'DepartureDateTime' => $this->travelApiDateTime((string) data_get($journey, 'segments.0.departure_at')),
                'OriginLocation' => ['LocationCode' => strtoupper((string) $journey['origin'])],
                'DestinationLocation' => ['LocationCode' => strtoupper((string) $journey['destination'])],
                'TPA_Extensions' => ['Flight' => collect($journey['segments'])->map(fn (array $segment): array => [
                    'Airline' => [
                        'Marketing' => (string) ($segment['marketing_airline'] ?? ''),
                        'Operating' => (string) ($segment['operating_airline'] ?? $segment['marketing_airline'] ?? ''),
                    ],
                    'Number' => (int) preg_replace('/^[A-Z0-9]{2}/', '', (string) ($segment['flight_number'] ?? '')),
                    'ClassOfService' => (string) ($segment['booking_code'] ?? ''),
                    'OriginLocation' => ['LocationCode' => strtoupper((string) $segment['origin'])],
                    'DestinationLocation' => ['LocationCode' => strtoupper((string) $segment['destination'])],
                    'DepartureDateTime' => $this->travelApiDateTime((string) $segment['departure_at']),
                    'ArrivalDateTime' => $this->travelApiDateTime((string) $segment['arrival_at']),
                    'Type' => 'A',
                ])->all()],
            ])->all(),
            'TravelPreferences' => [
                'TPA_Extensions' => ['VerificationItinCallLogic' => [
                    'Value' => 'L',
                ]],
            ],
            'TravelerInfoSummary' => [
                'SeatsRequested' => [(int) $search->adults + (int) $search->children],
                'AirTravelerAvail' => [['PassengerTypeQuantity' => collect($passengers)->map(fn (array $passenger): array => [
                    ...$passenger,
                    'TPA_Extensions' => ['VoluntaryChanges' => ['Match' => 'Info']],
                ])->all()]],
            ],
            'TPA_Extensions' => ['IntelliSellTransaction' => [
                'RequestType' => ['Name' => '50ITINS'],
            ]],
        ]];
    }

    /** @return \Illuminate\Support\Collection<int, array{origin:string,destination:string,segments:array<int, array<string,mixed>>}> */
    private function journeys(TravelOffer $offer): \Illuminate\Support\Collection
    {
        $segments = collect($offer->itinerary)->values();

        if ($segments->every(fn (array $segment): bool => array_key_exists('leg_index', $segment))) {
            return $segments->groupBy('leg_index')->values()->map(fn ($group): array => [
                'origin' => (string) data_get($group, '0.origin'),
                'destination' => (string) data_get($group, ($group->count() - 1).'.destination'),
                'segments' => $group->values()->all(),
            ]);
        }

        $requested = match ($offer->flightSearch->trip_type) {
            'round_trip' => [
                ['origin' => $offer->flightSearch->origin, 'destination' => $offer->flightSearch->destination],
                ['origin' => $offer->flightSearch->destination, 'destination' => $offer->flightSearch->origin],
            ],
            'multi_city' => (array) $offer->flightSearch->segments,
            default => [['origin' => $offer->flightSearch->origin, 'destination' => $offer->flightSearch->destination]],
        };
        $cursor = 0;

        return collect($requested)->map(function (array $leg) use ($segments, &$cursor): array {
            $journeySegments = [];
            while ($cursor < $segments->count()) {
                $segment = $segments[$cursor++];
                $journeySegments[] = $segment;
                if (strtoupper((string) $segment['destination']) === strtoupper((string) $leg['destination'])) {
                    break;
                }
            }

            return [
                'origin' => strtoupper((string) $leg['origin']),
                'destination' => strtoupper((string) $leg['destination']),
                'segments' => $journeySegments,
            ];
        })->filter(fn (array $journey): bool => $journey['segments'] !== [])->values();
    }

    private function travelApiDateTime(string $value): string
    {
        return CarbonImmutable::parse($value)->format('Y-m-d\TH:i:s');
    }
}

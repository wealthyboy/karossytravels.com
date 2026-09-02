<?php

namespace App\Travel\TravelApi;

final class TravelApiHotelSearchRequestBuilder
{
    public function __construct(private readonly array $configuration) {}

    /** @param array<string, mixed> $criteria */
    public function build(array $criteria): array
    {
        return ['GetHotelAvailRQ' => [
            'POS' => ['Source' => ['PseudoCityCode' => $this->configuration['pcc']]],
            'SearchCriteria' => [
                'OffSet' => 1,
                'SortBy' => 'AverageNightlyRate',
                'SortOrder' => 'ASC',
                'PageSize' => 50,
                'GeoSearch' => ['GeoRef' => [
                    'Radius' => 50, 'UOM' => 'MI',
                    'RefPoint' => ['Value' => $criteria['destination_code'], 'ValueContext' => 'CODE', 'RefPointType' => '6'],
                ]],
                'RateInfoRef' => [
                    'CurrencyCode' => 'USD', 'BestOnly' => '1',
                    'PrepaidQualifier' => 'IncludePrepaid', 'ConvertedRateInfoOnly' => true,
                    'StayDateTimeRange' => ['StartDate' => $criteria['check_in'], 'EndDate' => $criteria['check_out']],
                    'Rooms' => ['Room' => collect(range(1, (int) $criteria['rooms']))->map(fn (int $index): array => [
                        'Index' => $index,
                        'Adults' => max(1, intdiv((int) $criteria['adults'], (int) $criteria['rooms']) + ($index <= ((int) $criteria['adults'] % (int) $criteria['rooms']) ? 1 : 0)),
                        'Children' => (int) $criteria['children'],
                    ])->all()],
                    'RateSource' => '100',
                ],
            ],
        ]];
    }
}

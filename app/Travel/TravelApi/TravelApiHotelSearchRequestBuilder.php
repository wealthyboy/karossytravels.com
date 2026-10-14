<?php

namespace App\Travel\TravelApi;

final class TravelApiHotelSearchRequestBuilder
{
    public function __construct(private readonly array $configuration) {}

    /** @param array<string, mixed> $criteria */
    public function build(array $criteria): array
    {
        $rooms = (int) $criteria['rooms'];
        $children = (int) ($criteria['children'] ?? 0);
        $adults = (int) $criteria['adults'];
        $ages = $criteria['children_ages'] ?? [];
        if ($rooms < 1 || $adults < $rooms || $adults % $rooms !== 0 || $children % $rooms !== 0) {
            throw new \InvalidArgumentException('Sabre requires the same adult and child counts in every requested room.');
        }
        if (! is_array($ages) || count($ages) !== $children || ! array_is_list($ages)) {
            throw new \InvalidArgumentException('Select exactly one age for every child.');
        }
        foreach ($ages as $age) {
            if (is_bool($age) || filter_var($age, FILTER_VALIDATE_INT) === false || (int) $age < 0 || (int) $age > 17) {
                throw new \InvalidArgumentException('Child ages must be between 0 and 17.');
            }
        }
        $childrenPerRoom = intdiv($children, $rooms);
        // Sabre Get Hotel Avail v5 Room schema: ChildAges is a comma-separated
        // string, required with Children > 0. All rooms must have equal guest
        // counts. Keep age 0 as 0; supplier/hotel rules determine classification.
        // https://developer.sabre.com/rest-api/get-hotel-avail/v5.0/_attachments/spec.yml
        $occupancy = [];
        for ($index = 1; $index <= $rooms; $index++) {
            $room = ['Index' => $index, 'Adults' => intdiv($adults, $rooms), 'Children' => $childrenPerRoom];
            if ($childrenPerRoom > 0) {
                $room['ChildAges'] = implode(',', array_map('intval', array_slice($ages, ($index - 1) * $childrenPerRoom, $childrenPerRoom)));
            }
            $occupancy[] = $room;
        }

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
                    'Rooms' => ['Room' => $occupancy],
                    'RateSource' => '100',
                ],
            ],
        ]];
    }
}

<?php

namespace App\Travel\TravelApi;

final class TravelApiHotelAvailMapper
{
    /** @return array<int, array<string, mixed>> */
    public function map(array $response): array
    {
        $ratingKey = base64_decode('U2FicmVSYXRpbmc=');

        return collect(data_get($response, 'GetHotelAvailRS.HotelAvailInfos.HotelAvailInfo', []))
            ->map(function (array $hotel) use ($ratingKey): ?array {
                $info = $hotel['HotelInfo'] ?? [];
                $rateInfo = data_get($hotel, 'HotelRateInfo.RateInfos.ConvertedRateInfo.0', []);
                $room = data_get($hotel, 'HotelRateInfo.Rooms.Room.0', []);
                $plan = data_get($room, 'RatePlans.RatePlan.0', []);
                $price = $plan['ConvertedRateInfo'] ?? $rateInfo;
                $rateKey = $plan['RateKey'] ?? $rateInfo['RateKey'] ?? null;
                $total = $price['ApproxTotalPrice'] ?? $price['AmountAfterTax'] ?? null;

                if (! $rateKey || ! is_numeric($total)) {
                    return null;
                }

                return [
                    'hotel_code' => (string) ($info['HotelCode'] ?? ''),
                    'name' => (string) ($info['HotelName'] ?? 'Hotel'),
                    'rating' => isset($info[$ratingKey]) ? (float) $info[$ratingKey] : null,
                    'location' => [
                        'address' => data_get($info, 'LocationInfo.Address.AddressLine1'),
                        'city' => data_get($info, 'LocationInfo.Address.CityName.value'),
                        'country' => data_get($info, 'LocationInfo.Address.CountryName.value'),
                        'country_code' => data_get($info, 'LocationInfo.Address.CountryName.Code'),
                        'latitude' => data_get($info, 'LocationInfo.Latitude'),
                        'longitude' => data_get($info, 'LocationInfo.Longitude'),
                        'distance' => $info['Distance'] ?? null,
                        'distance_unit' => $info['UOM'] ?? null,
                    ],
                    'amenities' => collect(data_get($info, 'Amenities.Amenity', []))->pluck('Description')->filter()->take(8)->values()->all(),
                    'room_name' => $room['RoomType'] ?? null,
                    'rate_name' => $plan['RatePlanName'] ?? data_get($room, 'RoomDescription.Name'),
                    'rate_key' => (string) $rateKey,
                    'refundable' => (bool) data_get($price, 'CancelPenalties.CancelPenalty.0.Refundable', false),
                    'breakfast_included' => (bool) data_get($plan, 'MealsIncluded.Breakfast', false),
                    'currency' => strtoupper((string) ($price['CurrencyCode'] ?? 'USD')),
                    'total_minor' => (int) round((float) $total * 100),
                    'nightly_minor' => (int) round((float) ($price['AverageNightlyRate'] ?? 0) * 100),
                    'pricing' => [
                        'before_tax' => $price['AmountBeforeTax'] ?? null,
                        'after_tax' => $price['AmountAfterTax'] ?? null,
                        'tax_inclusive' => $price['TaxInclusive'] ?? null,
                    ],
                ];
            })->filter()->values()->all();
    }
}

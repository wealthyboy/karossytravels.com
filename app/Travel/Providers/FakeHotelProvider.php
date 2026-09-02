<?php

namespace App\Travel\Providers;

use App\Travel\Contracts\HotelProvider;

final class FakeHotelProvider implements HotelProvider
{
    public function name(): string { return 'fake'; }

    public function search(array $criteria): array
    {
        return [[
            'hotel_code' => 'KAR-001', 'name' => 'Karossy Grand Hotel', 'rating' => 4.5,
            'location' => ['address' => 'Victoria Island', 'city' => 'Lagos', 'country' => 'Nigeria', 'country_code' => 'NG', 'distance' => 2.4, 'distance_unit' => 'MI'],
            'amenities' => ['Free Wi-Fi', 'Breakfast', 'Swimming pool'],
            'room_name' => 'Deluxe King Room', 'rate_name' => 'Best Available Rate',
            'rate_key' => 'fake-hotel-rate-key', 'refundable' => true, 'breakfast_included' => true,
            'currency' => 'USD', 'total_minor' => 42000, 'nightly_minor' => 21000,
            'pricing' => ['before_tax' => '380.00', 'after_tax' => '420.00', 'tax_inclusive' => true],
        ]];
    }
}

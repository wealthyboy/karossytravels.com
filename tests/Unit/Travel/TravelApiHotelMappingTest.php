<?php

namespace Tests\Unit\Travel;

use App\Travel\TravelApi\TravelApiHotelAvailMapper;
use App\Travel\TravelApi\TravelApiHotelSearchRequestBuilder;
use PHPUnit\Framework\TestCase;

final class TravelApiHotelMappingTest extends TestCase
{
    public function test_it_builds_a_v5_hotel_availability_request(): void
    {
        $payload = (new TravelApiHotelSearchRequestBuilder(['pcc' => 'WD4H']))->build([
            'destination_code' => 'LOS', 'check_in' => '2026-09-11', 'check_out' => '2026-09-13',
            'adults' => 2, 'children' => 0, 'rooms' => 1,
        ]);

        $this->assertSame('WD4H', data_get($payload, 'GetHotelAvailRQ.POS.Source.PseudoCityCode'));
        $this->assertSame('LOS', data_get($payload, 'GetHotelAvailRQ.SearchCriteria.GeoSearch.GeoRef.RefPoint.Value'));
        $this->assertSame('2026-09-11', data_get($payload, 'GetHotelAvailRQ.SearchCriteria.RateInfoRef.StayDateTimeRange.StartDate'));
    }

    public function test_it_normalizes_a_travel_api_hotel_and_rate(): void
    {
        $response = ['GetHotelAvailRS' => ['HotelAvailInfos' => ['HotelAvailInfo' => [[
            'HotelInfo' => ['HotelCode' => '100', 'HotelName' => 'Karossy Hotel', base64_decode('U2FicmVSYXRpbmc=') => '4.5', 'LocationInfo' => ['Address' => ['AddressLine1' => 'Lagos Road', 'CityName' => ['value' => 'Lagos'], 'CountryName' => ['Code' => 'NG', 'value' => 'Nigeria']]]],
            'HotelRateInfo' => ['RateInfos' => ['ConvertedRateInfo' => [['RateKey' => 'secret-rate', 'ApproxTotalPrice' => '500.00', 'AverageNightlyRate' => '250.00', 'CurrencyCode' => 'USD']]], 'Rooms' => ['Room' => [['RoomType' => 'King Room', 'RatePlans' => ['RatePlan' => [['RatePlanName' => 'Breakfast Rate', 'RateKey' => 'secret-rate', 'MealsIncluded' => ['Breakfast' => true], 'ConvertedRateInfo' => ['ApproxTotalPrice' => '500.00', 'AverageNightlyRate' => '250.00', 'CurrencyCode' => 'USD', 'CancelPenalties' => ['CancelPenalty' => [['Refundable' => true]]]]]]]]]]],
        ]]]]];

        $offer = (new TravelApiHotelAvailMapper)->map($response)[0];

        $this->assertSame('Karossy Hotel', $offer['name']);
        $this->assertSame(50000, $offer['total_minor']);
        $this->assertTrue($offer['breakfast_included']);
        $this->assertTrue($offer['refundable']);
    }
}

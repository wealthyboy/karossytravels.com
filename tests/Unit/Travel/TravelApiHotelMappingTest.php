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

    public function test_it_preserves_every_room_rate_returned_for_a_property(): void
    {
        $response = ['GetHotelAvailRS' => ['HotelAvailInfos' => ['HotelAvailInfo' => [[
            'HotelInfo' => ['HotelCode' => '100', 'HotelName' => 'Karossy Hotel'],
            'HotelRateInfo' => ['Rooms' => ['Room' => [
                ['RoomType' => 'King Room', 'RatePlans' => ['RatePlan' => [
                    ['RatePlanName' => 'Room only', 'RateKey' => 'king-room', 'ConvertedRateInfo' => ['ApproxTotalPrice' => '400.00', 'AverageNightlyRate' => '200.00', 'CurrencyCode' => 'USD']],
                    ['RatePlanName' => 'Breakfast', 'RateKey' => 'king-breakfast', 'ConvertedRateInfo' => ['ApproxTotalPrice' => '450.00', 'AverageNightlyRate' => '225.00', 'CurrencyCode' => 'USD']],
                ]]],
                ['RoomType' => 'Twin Room', 'RatePlans' => ['RatePlan' => [[
                    'RatePlanName' => 'Flexible', 'RateKey' => 'twin-flexible', 'ConvertedRateInfo' => ['ApproxTotalPrice' => '500.00', 'AverageNightlyRate' => '250.00', 'CurrencyCode' => 'USD'],
                ]]]],
            ]]],
        ]]]]];

        $offers = (new TravelApiHotelAvailMapper)->map($response);

        $this->assertCount(3, $offers);
        $this->assertSame(['king-room', 'king-breakfast', 'twin-flexible'], array_column($offers, 'rate_key'));
        $this->assertSame(['King Room', 'King Room', 'Twin Room'], array_column($offers, 'room_name'));
    }
}

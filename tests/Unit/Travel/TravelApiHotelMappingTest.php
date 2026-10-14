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

    public function test_it_sends_exact_child_ages_in_the_sabre_v5_room_format(): void
    {
        $builder = new TravelApiHotelSearchRequestBuilder(['pcc' => 'WD4H']);
        $criteria = ['destination_code' => 'LOS', 'check_in' => '2026-11-01', 'check_out' => '2026-11-03', 'adults' => 2, 'children' => 2, 'children_ages' => [0, 17], 'rooms' => 1];
        $payload = $builder->build($criteria);
        $this->assertSame([['Index' => 1, 'Adults' => 2, 'Children' => 2, 'ChildAges' => '0,17']], data_get($payload, 'GetHotelAvailRQ.SearchCriteria.RateInfoRef.Rooms.Room'));
        $this->assertSame([['Index' => 1, 'Adults' => 2, 'Children' => 0]], data_get($builder->build([...$criteria, 'children' => 0, 'children_ages' => []]), 'GetHotelAvailRQ.SearchCriteria.RateInfoRef.Rooms.Room'));
    }

    public function test_it_does_not_repeat_all_children_in_every_room(): void
    {
        $payload = (new TravelApiHotelSearchRequestBuilder(['pcc' => 'WD4H']))->build(['destination_code' => 'LOS', 'check_in' => '2026-11-01', 'check_out' => '2026-11-03', 'adults' => 4, 'children' => 4, 'children_ages' => [0, 7, 10, 17], 'rooms' => 2]);
        $this->assertSame([
            ['Index' => 1, 'Adults' => 2, 'Children' => 2, 'ChildAges' => '0,7'],
            ['Index' => 2, 'Adults' => 2, 'Children' => 2, 'ChildAges' => '10,17'],
        ], data_get($payload, 'GetHotelAvailRQ.SearchCriteria.RateInfoRef.Rooms.Room'));
    }

    public function test_builder_never_invents_an_age_for_a_child(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new TravelApiHotelSearchRequestBuilder(['pcc' => 'WD4H']))->build(['destination_code' => 'LOS', 'check_in' => '2026-11-01', 'check_out' => '2026-11-03', 'adults' => 2, 'children' => 1, 'rooms' => 1]);
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

<?php

namespace Tests\Unit\Travel;

use App\Models\FlightSearch;
use App\Models\TravelOffer;
use App\Travel\FlightRevalidationService;
use App\Travel\TravelApi\TravelApiFlightRevalidationRequestBuilder;
use App\Travel\TravelApi\TravelApiFlightSearchRequestBuilder;
use App\Travel\TravelApi\TravelApiGroupedItineraryMapper;
use PHPUnit\Framework\TestCase;

final class TravelApiFlightMappingTest extends TestCase
{
    public function test_revalidation_accepts_travel_api_corrected_connection_times_for_the_same_flights(): void
    {
        $stored = [
            ['origin' => 'LOS', 'destination' => 'KGL', 'flight_number' => 'WB203', 'marketing_airline' => 'WB', 'departure_at' => '2026-08-06T14:45:00+01:00'],
            ['origin' => 'KGL', 'destination' => 'LHR', 'flight_number' => 'WB710', 'marketing_airline' => 'WB', 'departure_at' => '2026-08-06T01:15:00+02:00'],
        ];
        $validated = [
            ['origin' => 'LOS', 'destination' => 'KGL', 'flight_number' => 'WB203', 'marketing_airline' => 'WB', 'departure_at' => '2026-08-06T14:45:00+01:00'],
            ['origin' => 'KGL', 'destination' => 'LHR', 'flight_number' => 'WB710', 'marketing_airline' => 'WB', 'departure_at' => '2026-08-06T23:35:00+02:00'],
        ];

        $service = (new \ReflectionClass(FlightRevalidationService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(FlightRevalidationService::class, 'sameFlights');

        $this->assertTrue($method->invoke($service, $stored, $validated));
    }

    public function test_it_builds_a_round_trip_bfm_request(): void
    {
        $builder = new TravelApiFlightSearchRequestBuilder([
            'pcc' => 'TEST',
            'requestor_id' => '1',
            'company_code' => 'TN',
            'max_itineraries' => 50,
        ]);

        $payload = $builder->build([
            'origin' => 'WAW',
            'destination' => 'SPU',
            'departure_date' => '2026-09-11',
            'return_date' => '2026-09-18',
            'trip_type' => 'round_trip',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ]);

        $request = $payload['OTA_AirLowFareSearchRQ'];
        $this->assertSame('TEST', $request['POS']['Source'][0]['PseudoCityCode']);
        $this->assertCount(2, $request['OriginDestinationInformation']);
        $this->assertSame('WAW', $request['OriginDestinationInformation'][0]['OriginLocation']['LocationCode']);
        $this->assertSame('WAW', $request['OriginDestinationInformation'][1]['DestinationLocation']['LocationCode']);
        $this->assertSame('50ITINS', $request['TPA_Extensions']['IntelliSellTransaction']['RequestType']['Name']);
    }

    public function test_it_resolves_grouped_itinerary_references_into_an_offer(): void
    {
        $response = ['groupedItineraryResponse' => [
            'scheduleDescs' => [[
                'id' => 1, 'stopCount' => 0, 'elapsedTime' => 120,
                'departure' => ['airport' => 'WAW', 'time' => '14:20:00+02:00'],
                'arrival' => ['airport' => 'SPU', 'time' => '16:20:00+02:00'],
                'carrier' => ['marketing' => 'LO', 'marketingFlightNumber' => 575, 'operating' => 'LO', 'equipment' => ['code' => 'E75']],
            ]],
            'legDescs' => [['id' => 1, 'schedules' => [['ref' => 1]]]],
            'baggageAllowanceDescs' => [['id' => 1, 'pieceCount' => 0]],
            'itineraryGroups' => [[
                'groupDescription' => ['legDescriptions' => [['departureDate' => '2026-09-11']]],
                'itineraries' => [[
                    'id' => 7, 'pricingSource' => 'ADVJR1', 'legs' => [['ref' => 1]],
                    'pricingInformation' => [['fare' => [
                        'validatingCarrierCode' => 'LO',
                        'totalFare' => ['totalPrice' => 131.80, 'totalTaxAmount' => 73.80, 'currency' => 'USD'],
                        'passengerInfoList' => [['passengerInfo' => [
                            'nonRefundable' => true,
                            'fareComponents' => [['segments' => [['segment' => ['bookingCode' => 'O', 'cabinCode' => 'Y', 'seatsAvailable' => 9]]]]],
                            'baggageInformation' => [['segments' => [['id' => 0]], 'allowance' => ['ref' => 1]]],
                        ]]],
                    ]]],
                ]],
            ]],
        ]];

        $offers = (new TravelApiGroupedItineraryMapper)->map($response);

        $this->assertCount(1, $offers);
        $this->assertSame('USD', $offers[0]['price']['currency']);
        $this->assertSame(13180, $offers[0]['price']['total_minor']);
        $this->assertSame(7380, $offers[0]['price']['taxes_minor']);
        $this->assertSame('LO575', $offers[0]['segments'][0]['flight_number']);
        $this->assertSame(0, $offers[0]['segments'][0]['leg_index']);
        $this->assertSame(0, $offers[0]['segments'][0]['checked_baggage_pieces']);
        $this->assertFalse($offers[0]['refundable']);
    }

    public function test_revalidation_uses_plain_local_timestamps_and_groups_round_trip_legs(): void
    {
        $search = new FlightSearch([
            'origin' => 'LOS', 'destination' => 'LHR', 'trip_type' => 'round_trip',
            'adults' => 1, 'children' => 0, 'infants' => 0,
        ]);
        $offer = new TravelOffer(['itinerary' => [
            ['leg_index' => 0, 'origin' => 'LOS', 'destination' => 'ADD', 'departure_at' => '2026-08-06T08:55:00+01:00', 'arrival_at' => '2026-08-06T18:15:00+03:00', 'flight_number' => 'ET900', 'marketing_airline' => 'ET', 'operating_airline' => 'ET', 'booking_code' => 'L'],
            ['leg_index' => 0, 'origin' => 'ADD', 'destination' => 'LHR', 'departure_at' => '2026-08-07T01:10:00+03:00', 'arrival_at' => '2026-08-07T06:25:00+01:00', 'flight_number' => 'ET700', 'marketing_airline' => 'ET', 'operating_airline' => 'ET', 'booking_code' => 'L'],
            ['leg_index' => 1, 'origin' => 'LHR', 'destination' => 'LOS', 'departure_at' => '2026-08-13T15:35:00+01:00', 'arrival_at' => '2026-08-13T21:45:00+01:00', 'flight_number' => 'ET901', 'marketing_airline' => 'ET', 'operating_airline' => 'ET', 'booking_code' => 'L'],
        ]]);
        $offer->setRelation('flightSearch', $search);

        $request = (new TravelApiFlightRevalidationRequestBuilder(['pcc' => 'TEST']))->build($offer)['OTA_AirLowFareSearchRQ'];

        $this->assertCount(2, $request['OriginDestinationInformation']);
        $this->assertCount(2, $request['OriginDestinationInformation'][0]['TPA_Extensions']['Flight']);
        $this->assertFalse($request['OriginDestinationInformation'][0]['Fixed']);
        $this->assertSame('L', $request['TravelPreferences']['TPA_Extensions']['VerificationItinCallLogic']['Value']);
        $this->assertSame('50ITINS', $request['TPA_Extensions']['IntelliSellTransaction']['RequestType']['Name']);
        $this->assertSame('2026-08-06T08:55:00', $request['OriginDestinationInformation'][0]['DepartureDateTime']);
        $this->assertSame('2026-08-06T18:15:00', $request['OriginDestinationInformation'][0]['TPA_Extensions']['Flight'][0]['ArrivalDateTime']);
        $this->assertStringNotContainsString('+', json_encode($request, JSON_THROW_ON_ERROR));
    }
}

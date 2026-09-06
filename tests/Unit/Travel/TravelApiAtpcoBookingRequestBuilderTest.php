<?php

namespace Tests\Unit\Travel;

use App\Models\Customer;
use App\Models\TravelOffer;
use App\Travel\TravelApi\TravelApiAtpcoBookingRequestBuilder;
use Tests\TestCase;

final class TravelApiAtpcoBookingRequestBuilderTest extends TestCase
{
    public function test_it_matches_the_provider_validated_booking_shape(): void
    {
        $offer = new TravelOffer([
            'fare_summary' => ['validating_airline' => 'PR'],
            'itinerary' => [[
                'origin' => 'MNL', 'destination' => 'BKK',
                'departure_at' => '2026-09-04T09:40:00+08:00',
                'flight_number' => 'PR730', 'marketing_airline' => 'PR',
                'booking_code' => 'T', 'fare_basis_code' => 'TOTTH',
            ]],
        ]);
        $customer = new Customer(['email' => 'traveler@example.com', 'phone' => '+65 1234 5678']);
        $travellers = [[
            'first_name' => 'Ricky', 'last_name' => 'Jones', 'type' => 'ADT',
            'date_of_birth' => '1960-02-08', 'gender' => 'male',
            'passport_number' => 'YG20770658', 'passport_expiry' => '2030-04-20',
            'passport_country' => 'SG', 'nationality' => 'SG',
        ]];

        $payload = (new TravelApiAtpcoBookingRequestBuilder)->build($offer, $customer, $travellers);

        $this->assertSame(['ticketingPolicy' => 'TODAY'], $payload['agency']);
        $this->assertSame('Passenger1', $payload['travelers'][0]['id']);
        $this->assertSame('CTCE', $payload['travelers'][0]['specialServices'][1]['code']);
        $this->assertSame(['NO', 'UC', 'US', 'UN', 'UU', 'LL', 'HL'], $payload['flightDetails']['haltOnFlightStatusCodes']);
        $this->assertSame('TOTTH', $payload['flightDetails']['flightPricing'][0]['qualifiers']['specificFares'][0]['fareBasisCode']);
        $this->assertSame([1], $payload['flightDetails']['flightPricing'][0]['qualifiers']['specificFares'][0]['flightIndices']);
        $this->assertSame([1], $payload['flightDetails']['flightPricing'][0]['qualifiers']['travelerIndices']);
        $this->assertSame(3000, $payload['asynchronousUpdateWaitTime']);
        $this->assertSame(['HALT_ON_ERROR'], $payload['errorHandlingPolicy']);
    }
}

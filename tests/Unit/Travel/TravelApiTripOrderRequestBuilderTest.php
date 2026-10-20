<?php

namespace Tests\Unit\Travel;

use App\Models\Customer;
use App\Models\TravelOffer;
use App\Travel\TravelApi\TravelApiTripOrderRequestBuilder;
use Tests\TestCase;

final class TravelApiTripOrderRequestBuilderTest extends TestCase
{
    public function test_passport_is_omitted_when_absent_and_preserved_when_provided(): void
    {
        $offer = new TravelOffer(['fare_summary' => ['order_offer_id' => 'offer-test', 'selected_offer_item_ids' => ['item-test']]]);
        $customer = new Customer(['email' => 'test@example.com', 'phone' => '+2348000000000']);
        $traveller = ['type' => 'ADT', 'first_name' => 'Test', 'last_name' => 'Guest', 'date_of_birth' => '1990-01-01', 'gender' => 'male', 'nationality' => 'NG'];
        $builder = new TravelApiTripOrderRequestBuilder;
        $passenger = $builder->build($offer, $customer, [$traveller])['passengers'][0];
        $this->assertSame('TEST', $passenger['givenName']);
        $this->assertArrayNotHasKey('identityDocuments', $passenger);

        $traveller += ['passport_number' => 'a12345678', 'passport_country' => 'NG', 'passport_expiry' => '2030-01-01'];
        $passenger = $builder->build($offer, $customer, [$traveller])['passengers'][0];
        $this->assertSame('A12345678', $passenger['identityDocuments'][0]['documentNumber']);
        $this->assertSame('PT', $passenger['identityDocuments'][0]['documentTypeCode']);
    }
}

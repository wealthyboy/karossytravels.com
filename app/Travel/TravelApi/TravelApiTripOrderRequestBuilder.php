<?php

namespace App\Travel\TravelApi;

use App\Models\Customer;
use App\Models\TravelOffer;
use RuntimeException;

final class TravelApiTripOrderRequestBuilder
{
    /** @param array<int, array<string, mixed>> $travellers @return array<string, mixed> */
    public function build(TravelOffer $offer, Customer $customer, array $travellers): array
    {
        $offerId = data_get($offer->fare_summary, 'order_offer_id');
        $itemIds = (array) data_get($offer->fare_summary, 'selected_offer_item_ids', []);
        if (blank($offerId) || $itemIds === []) {
            throw new RuntimeException('The live fare was revalidated, but the required order identifiers were not returned.');
        }

        return [
            'transactionOptions' => [
                'requestType' => 'STATEFUL',
                'commitTransaction' => true,
                'movePassengerDetails' => true,
                'intialIgnore' => true,
            ],
            'createOrders' => [[
                'offerId' => $offerId,
                'selectedOfferItems' => collect($itemIds)->map(fn (string $id): array => ['id' => $id])->all(),
            ]],
            'contactInfos' => [[
                'id' => 'CI-1',
                'phones' => [['number' => preg_replace('/\D+/', '', (string) $customer->phone)]],
                'emails' => [['address' => $customer->email]],
            ]],
            'passengers' => collect($travellers)->values()->map(fn (array $traveller, int $index): array => [
                'id' => 'Passenger'.($index + 1),
                'typeCode' => $traveller['type'],
                'contactInfoRefId' => 'CI-1',
                'birthdate' => $traveller['date_of_birth'],
                'genderCode' => match ($traveller['gender']) { 'male' => 'M', 'female' => 'F', default => 'U' },
                'givenName' => strtoupper($traveller['first_name']),
                'surname' => strtoupper($traveller['last_name']),
                'identityDocuments' => [[
                    'documentNumber' => strtoupper($traveller['passport_number']),
                    'documentTypeCode' => 'PT',
                    'issuingCountryCode' => strtoupper($traveller['passport_country']),
                    'citizenshipCountryCode' => strtoupper($traveller['nationality']),
                    'expiryDate' => $traveller['passport_expiry'],
                    'givenName' => strtoupper($traveller['first_name']),
                    'surname' => strtoupper($traveller['last_name']),
                    'birthdate' => $traveller['date_of_birth'],
                    'genderCode' => match ($traveller['gender']) { 'male' => 'M', 'female' => 'F', default => 'U' },
                ]],
            ])->all(),
        ];
    }
}

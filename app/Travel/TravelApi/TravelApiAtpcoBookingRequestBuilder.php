<?php

namespace App\Travel\TravelApi;

use App\Models\Customer;
use App\Models\TravelOffer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Builds a /v1/trip/orders/createBooking payload for traditional ATPCO content.
 *
 * Used when revalidation did not return NDC offerId / selectedOfferItems, which
 * means the airline distributes through the legacy GDS availability/pricing flow.
 *
 * Schema source: TravelApi Booking Management API Postman collection (2026.04)
 * https://github.com/TravelApiDevStudio/postman-collections/tree/master/Booking-Management
 */
final class TravelApiAtpcoBookingRequestBuilder
{
    /** @param array<string, mixed> $configuration */
    public function __construct(private readonly array $configuration = []) {}

    /** @param array<int, array<string, mixed>> $travellers @return array<string, mixed> */
    public function build(TravelOffer $offer, Customer $customer, array $travellers, ?string $agencyNumberOverride = null): array
    {
        $agencyNumber = strtoupper((string) ($agencyNumberOverride ?? $this->configuration['agency_number'] ?? ''));

        $agencyState = $this->configuration['agency_state'] ?? config('services.travel.travel_api.agency_state') ?? 'Lagos';

        $agency = [
            'address' => array_filter([
                'name'        => config('app.name'),
                'street'      => '1 Karossy Way',
                'city'        => 'Lagos',
                'stateProvince'=> $agencyState ?: null,
                'postalCode'  => '100001',
                'countryCode' => 'NG',
                'freeText'    => config('app.name')."\nLagos, NG",
            ]),
            'ticketingPolicy' => 'TODAY',
        ];

        // TravelApi expects agencyCustomerNumber to follow their pattern.
        // Pattern: ^[0-9A-Z]{6}([1-9A-Z*]{1}|[0-9A-Z]{4})?$
        $travelApiAgencyRegex = '/^[0-9A-Z]{6}([1-9A-Z\*]{1}|[0-9A-Z]{4})?$/';
        if ($agencyNumber !== '') {
            if (preg_match($travelApiAgencyRegex, $agencyNumber)) {
                $agency['agencyCustomerNumber'] = $agencyNumber;
            } else {
                Log::warning('Travel API agency number omitted because its format was invalid.', ['provided' => $agencyNumber]);
            }
        }

        // Ensure we never send an empty agencyCustomerNumber key
        if (isset($agency['agencyCustomerNumber']) && ($agency['agencyCustomerNumber'] === '' || $agency['agencyCustomerNumber'] === null)) {
            unset($agency['agencyCustomerNumber']);
        }

        return [
            'agency' => $agency,

            'travelers'   => $this->buildTravelers($travellers),
            'contactInfo' => [
                'emails' => [$customer->email],
                'phones' => [preg_replace('/\D+/', '', (string) $customer->phone)],
            ],

            'flightDetails' => [
                'flights'        => $this->buildFlights($offer),
                'flightPricing'  => $this->buildPricing($offer),
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $travellers @return array<int, array<string, mixed>> */
    private function buildTravelers(array $travellers): array
    {
        return collect($travellers)->values()->map(function (array $t): array {
            $traveler = [
                'givenName'     => strtoupper($t['first_name']),
                'surname'       => strtoupper($t['last_name']),
                'birthDate'     => $t['date_of_birth'],
                'passengerCode' => $t['type'],
            ];

            if (! empty($t['title'])) {
                $traveler['namePrefix'] = $t['title'];
            }

            // Add passport details when present
                if (! empty($t['passport_number'])) {
                    $traveler['identityDocuments'] = [[
                        'documentType'           => 'PASSPORT',
                        'documentNumber'         => strtoupper($t['passport_number']),
                        'expiryDate'             => $t['passport_expiry'],
                        // TravelApi expects explicit country code fields
                        'issuingCountryCode'     => strtoupper($t['passport_country'] ?? ''),
                        'residenceCountryCode'   => strtoupper($t['nationality'] ?? ''),
                        'citizenshipCountryCode' => strtoupper($t['nationality'] ?? ''),
                        'givenName'              => strtoupper($t['first_name']),
                        'surname'                => strtoupper($t['last_name']),
                        'birthDate'              => $t['date_of_birth'],
                        // Normalize a variety of gender inputs to TravelApi's expected enums
                        'gender'                 => match (strtolower((string) ($t['gender'] ?? ''))) {
                            'male', 'm', 'ma' => 'MALE',
                            'female', 'f', 'fe' => 'FEMALE',
                            default => 'UNDISCLOSED',
                        },
                    ]];
            }

            return $traveler;
        })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function buildFlights(TravelOffer $offer): array
    {
        return collect($offer->itinerary)->values()->map(function (array $segment): array {
            $departure = CarbonImmutable::parse((string) $segment['departure_at']);

            // Extract numeric-only flight number (e.g. "WB203" → 203)
            $flightNum = (int) preg_replace('/^[A-Z]{2}/', '', strtoupper((string) ($segment['flight_number'] ?? '')));

            return [
                'flightNumber'     => $flightNum,
                'airlineCode'      => strtoupper((string) ($segment['marketing_airline'] ?? '')),
                'fromAirportCode'  => strtoupper((string) ($segment['origin'] ?? '')),
                'toAirportCode'    => strtoupper((string) ($segment['destination'] ?? '')),
                'departureDate'    => $departure->toDateString(),
                'departureTime'    => $departure->format('H:i'),
                'bookingClass'     => strtoupper((string) ($segment['booking_code'] ?? 'Y')),
                'isMarriageGroup'  => false,
                'flightStatusCode' => 'NN',
            ];
        })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function buildPricing(TravelOffer $offer): array
    {
        $validatingAirline = (string) data_get($offer->fare_summary, 'validating_airline', '');

        $qualifier = ['flightIndices' => range(1, count($offer->itinerary))];

        if ($validatingAirline !== '') {
            $qualifier['validatingAirlineCode'] = $validatingAirline;
        }

        return [['qualifiers' => $qualifier]];
    }
}

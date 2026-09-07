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

        // Keep the agency block identical to the provider-validated request.
        // Agency identity is resolved from the authenticated PCC.
        $agency = ['ticketingPolicy' => 'TODAY'];

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
            'contactInfo' => [
                'emails' => [$customer->email],
                'phones' => [preg_replace('/\D+/', '', (string) $customer->phone)],
            ],
            'travelers'   => $this->buildTravelers($travellers, $customer),
            'flightDetails' => [
                'haltOnFlightStatusCodes' => ['NO', 'UC', 'US', 'UN', 'UU', 'LL', 'HL'],
                'flights' => $this->buildFlights($offer),
                'flightPricing' => $this->buildPricing($offer, $travellers),
            ],
            'asynchronousUpdateWaitTime' => 3000,
            'receivedFrom' => (string) ($this->configuration['received_from'] ?? 'KAROSSY'),
            'errorHandlingPolicy' => ['HALT_ON_ERROR'],
        ];
    }

    /** @param array<int, array<string, mixed>> $travellers @return array<int, array<string, mixed>> */
    private function buildTravelers(array $travellers, Customer $customer): array
    {
        $phone = preg_replace('/\D+/', '', (string) $customer->phone);

        return collect($travellers)->values()->map(function (array $t, int $index) use ($customer, $phone): array {
            $traveler = [
                'id' => 'Passenger'.($index + 1),
                'givenName'     => strtoupper($t['first_name']),
                'surname'       => strtoupper($t['last_name']),
                'birthDate'     => $t['date_of_birth'],
                'passengerCode' => $t['type'],
                'nameReferenceCode' => '',
                'phones' => [['number' => $phone]],
                'emails' => [$customer->email],
                'specialServices' => [
                    ['code' => 'CTCM', 'message' => $phone],
                    ['code' => 'CTCE', 'message' => str_replace('@', '//', $customer->email)],
                ],
            ];

            if (! empty($t['title'])) {
                $traveler['namePrefix'] = $t['title'];
            }

            // Keep the identity-document object limited to fields accepted by
            // the provider-validated createBooking contract.
            if (! empty($t['passport_number'])) {
                $traveler['identityDocuments'] = [[
                    'documentNumber' => strtoupper($t['passport_number']),
                    'documentType' => 'PASSPORT',
                    'expiryDate' => $t['passport_expiry'],
                    'issuingCountryCode' => strtoupper($t['passport_country'] ?? ''),
                    'residenceCountryCode' => strtoupper($t['nationality'] ?? ''),
                    'birthDate' => $t['date_of_birth'],
                    'gender' => match (strtolower((string) ($t['gender'] ?? ''))) {
                        'male', 'm', 'ma' => 'MALE',
                        'female', 'f', 'fe' => 'FEMALE',
                        default => 'UNDISCLOSED',
                    },
                    'givenName' => strtoupper($t['first_name']),
                    'surname' => strtoupper($t['last_name']),
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

            // createBooking defines the flight number as a string without the
            // marketing carrier prefix. Preserve an already-numeric value.
            $airlineCode = strtoupper(trim((string) ($segment['marketing_airline'] ?? '')));
            $flightNum = strtoupper(trim((string) ($segment['flight_number'] ?? '')));
            if ($airlineCode !== '' && str_starts_with($flightNum, $airlineCode)) {
                $flightNum = substr($flightNum, strlen($airlineCode));
            }

            return [
                'flightNumber'     => $flightNum,
                'airlineCode'      => $airlineCode,
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
    private function buildPricing(TravelOffer $offer, array $travellers): array
    {
        $qualifier = [
            'travelerIndices' => range(1, count($travellers)),
            'passengersPricing' => collect($travellers)
                ->countBy(fn (array $traveller): string => (string) $traveller['type'])
                ->map(fn (int $count, string $code): array => [
                    'passengerCode' => $code,
                    'numberOfpassengers' => $count,
                ])->values()->all(),
            'specificFares' => collect($offer->itinerary)->values()
                ->map(fn (array $segment, int $index): array => [
                    'fareBasisCode' => strtoupper((string) ($segment['fare_basis_code'] ?? '')),
                    'flightIndex' => $index + 1,
                ])->filter(fn (array $fare): bool => $fare['fareBasisCode'] !== '')
                ->groupBy('fareBasisCode')
                ->map(fn ($segments, string $fareBasisCode): array => [
                    'fareBasisCode' => $fareBasisCode,
                    'flightIndices' => $segments->pluck('flightIndex')->map(fn (int $index): string => (string) $index)->values()->all(),
                ])->values()->all(),
        ];

        return [['qualifiers' => $qualifier]];
    }
}

<?php

namespace App\Travel\TravelApi;

use App\Models\Customer;
use App\Models\HotelOffer;
use RuntimeException;

final class TravelApiHotelBookingRequestBuilder
{
    /** @param array<string, mixed> $configuration */
    public function __construct(private readonly array $configuration = []) {}

    /** @return array<string, mixed> */
    public function priceCheck(HotelOffer $offer): array
    {
        $offer->loadMissing('search');
        $search = $offer->search;

        if ($search === null) {
            throw new RuntimeException('The hotel offer is missing its search context. Search again before checkout.');
        }

        $rateKey = trim((string) $offer->rate_key);
        if ($rateKey === '') {
            throw new RuntimeException('This hotel offer does not contain the supplier RateKey required for price check. Search again.');
        }

        $pcc = strtoupper(trim((string) ($this->configuration['pcc'] ?? '')));
        if ($pcc === '') {
            throw new RuntimeException('The Sabre pseudo city code is not configured for hotel price check.');
        }

        $rooms = max(1, (int) $search->rooms);
        $adults = max(1, (int) $search->adults);
        $children = max(0, (int) $search->children);

        // Hotel Price Check must retain the stay and occupancy context that
        // produced the v5 RateKey. Keep the room distribution aligned with
        // the existing GetHotelAvailRQ builder so Sabre validates the same
        // product the customer selected.
        $roomPayload = collect(range(1, $rooms))->map(function (int $index) use ($adults, $children, $rooms): array {
            return [
                'Index' => $index,
                'Adults' => max(1, intdiv($adults, $rooms) + ($index <= ($adults % $rooms) ? 1 : 0)),
                'Children' => $children,
            ];
        })->all();

        return [
            'HotelPriceCheckRQ' => [
                'POS' => [
                    'Source' => [
                        'PseudoCityCode' => $pcc,
                    ],
                ],
                'RateInfoRef' => [
                    'RateKey' => $rateKey,
                    'Rooms' => [
                        'Room' => $roomPayload,
                    ],
                    'StayDateTimeRange' => [
                        'StartDate' => $search->check_in?->format('Y-m-d'),
                        'EndDate' => $search->check_out?->format('Y-m-d'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $priceCheckResponse
     * @return array<string, mixed>
     */
    public function booking(
        HotelOffer $offer,
        Customer $customer,
        string $bookingKey,
        array $priceCheckResponse,
        ?string $specialRequests = null,
    ): array {
        $customerPhone = preg_replace('/\D+/', '', (string) $customer->phone) ?: '';
        $firstName = strtoupper(trim((string) $customer->first_name));
        $lastName = strtoupper(trim((string) $customer->last_name));
        $email = strtolower(trim((string) $customer->email));
        $agencyName = trim((string) ($this->configuration['agency_name'] ?? config('app.name', 'Karossy Travels')));
        $agencyCity = trim((string) ($this->configuration['agency_city'] ?? 'Lagos'));
        $agencyCountryCode = strtoupper(trim((string) ($this->configuration['agency_country_code'] ?? 'NG')));
        $pcc = strtoupper(trim((string) ($this->configuration['pcc'] ?? '')));

        if ($firstName === '' || $lastName === '') {
            throw new RuntimeException('A lead guest first name and last name are required for hotel confirmation.');
        }

        if ($email === '') {
            throw new RuntimeException('A lead guest email address is required for hotel confirmation.');
        }

        $customerInfo = [
            'Email' => [[
                'Address' => $email,
                'NameNumber' => '1.1',
            ]],
            'PersonName' => [[
                'GivenName' => $firstName,
                'Surname' => $lastName,
                'Infant' => false,
                'NameNumber' => '1.1',
                'PassengerType' => 'ADT',
            ]],
        ];

        // Do not invent contact data for Sabre. Website checkout validates a
        // phone number, while an older admin customer may legitimately have no
        // phone stored. In that case omit ContactNumbers instead of sending a
        // fake value that could make the supplier request invalid.
        if ($customerPhone !== '') {
            $customerInfo['ContactNumbers'] = [
                'ContactNumber' => [[
                    'NameNumber' => '1.1',
                    'Phone' => $customerPhone,
                    'PhoneUseType' => 'H',
                ]],
            ];
        }

        $request = [
            'CreatePassengerNameRecordRQ' => [
                'TravelItineraryAddInfo' => [
                    'AgencyInfo' => [
                        'Address' => array_filter([
                            'AddressLine' => $agencyName !== '' ? $agencyName : 'Karossy Travels',
                            'CityName' => $agencyCity !== '' ? $agencyCity : 'Lagos',
                            'CountryCode' => $agencyCountryCode !== '' ? $agencyCountryCode : 'NG',
                            'PostalCode' => $this->nonBlank('agency_postal_code'),
                            'StreetNmbr' => $this->nonBlank('agency_street'),
                        ], fn (mixed $value): bool => $value !== null && $value !== ''),
                    ],
                    'CustomerInfo' => $customerInfo,
                ],
                'HotelBook' => $this->hotelBook($offer, $customer, $bookingKey, $priceCheckResponse, $specialRequests),
                'PostProcessing' => [
                    'EndTransaction' => [
                        'Source' => [
                            'ReceivedFrom' => $agencyName !== '' ? $agencyName : 'KAROSSY TRAVELS',
                        ],
                    ],
                ],
                'haltOnHotelBookError' => true,
                'haltWhenNoHotelCF' => true,
                'version' => '2.5.0',
            ],
        ];

        if ($pcc !== '') {
            $request['CreatePassengerNameRecordRQ']['targetCity'] = $pcc;
        }

        return $request;
    }

    /**
     * Price Check already proves that Sabre can resolve the selected RateKey
     * into a BookingKey. Do not infer extra agency/payment requirements here.
     * The supplier is the source of truth for the final booking contract and
     * will return an authoritative error if a guarantee/FOP is required.
     *
     * @param array<string, mixed> $priceCheckResponse
     */
    public function assertBookable(array $priceCheckResponse): void
    {
        if ($priceCheckResponse === []) {
            throw new RuntimeException('The hotel price check response was empty. Search again before booking.');
        }
    }

    /**
     * @param array<string, mixed> $priceCheckResponse
     * @return array<string, mixed>
     */
    private function hotelBook(HotelOffer $offer, Customer $customer, string $bookingKey, array $priceCheckResponse, ?string $specialRequests): array
    {
        $phone = preg_replace('/\D+/', '', (string) $customer->phone) ?: null;
        $guest = array_filter([
            'Type' => 10,
            'Index' => 1,
            'LeadGuest' => true,
            'FirstName' => trim((string) $customer->first_name),
            'LastName' => trim((string) $customer->last_name),
            'Email' => strtolower(trim((string) $customer->email)),
            'Contact' => $phone ? ['Mobile' => $phone] : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $hotelBook = [
            'BookingInfo' => ['BookingKey' => $bookingKey],
            'Rooms' => [
                'Room' => [[
                    'Guests' => ['Guest' => [$guest]],
                ]],
                'NumberOfRooms' => max(1, (int) $offer->search->rooms),
            ],
            'bookGDSviaCSL' => true,
        ];

        $paymentInformation = $this->paymentInformation($priceCheckResponse);
        if ($paymentInformation !== null) {
            $hotelBook['PaymentInformation'] = $paymentInformation;
        }

        if (filled($specialRequests)) {
            $hotelBook['SpecialInstructions'] = [
                'SpecialInstruction' => [trim((string) $specialRequests)],
            ];
        }

        $pos = $this->pointOfSale();
        if ($pos !== null) {
            $hotelBook['POS'] = $pos;
        }

        return $hotelBook;
    }

    /**
     * Select a supplier-compatible hotel form of payment from the latest
     * price-check response. Karossy never invents card data. If a property
     * requires a card and does not accept an agency/IATA guarantee, fail before
     * a local booking is marked confirmed.
     *
     * @param array<string, mixed> $priceCheckResponse
     * @return array<string, mixed>|null
     */
    private function paymentInformation(array $priceCheckResponse): ?array
    {
        $guarantee = $this->firstNestedValueForKey($priceCheckResponse, 'Guarantee');

        // Current CPNR schema permits PaymentInformation to be omitted for a
        // pay-later rate. If the property requires a guarantee, the price-check
        // response supplies Guarantee information and accepted FOP types.
        if (! is_array($guarantee) || $guarantee === []) {
            return null;
        }

        $rawType = strtoupper(trim((string) ($guarantee['GuaranteeType'] ?? $guarantee['Type'] ?? '')));
        if (in_array($rawType, ['', 'NONE', 'NOT REQUIRED', 'NOT_REQUIRED'], true)) {
            return null;
        }

        $paymentType = str_contains($rawType, 'DEP') ? 'DEPOSIT' : 'GUARANTEE';
        $accepted = $this->acceptedGuarantees($guarantee);
        $iataNumber = $this->iataNumber();

        // Only add a form of payment when Karossy actually has the required
        // agency data and the price-check response clearly advertises support
        // for that form. Never fabricate an IATA number or card details.
        if (! str_contains($rawType, 'PREPAY')
            && $paymentType === 'GUARANTEE'
            && $iataNumber !== ''
            && ($accepted === [] || $this->accepts($accepted, 19, 'IATA'))) {
            return [
                'Type' => 'GUARANTEE',
                'FormOfPayment' => [
                    'Agency' => ['IATANumber' => $iataNumber],
                ],
            ];
        }

        if (! str_contains($rawType, 'PREPAY') && $this->accepts($accepted, 18, 'AGENCY')) {
            return [
                'Type' => $paymentType,
                'FormOfPayment' => [
                    'Agency' => [
                        'Name' => trim((string) ($this->configuration['agency_name'] ?? config('app.name', 'Karossy Travels'))),
                        'Address' => $this->agencyPaymentAddress(),
                    ],
                ],
            ];
        }

        // For card-only, prepay, or otherwise unrecognised guarantee rules,
        // omit PaymentInformation and let Sabre validate the final booking.
        // This avoids Karossy inventing supplier requirements before Sabre has
        // actually evaluated the BookingKey.
        return null;
    }

    /** @return array<int, array<string, mixed>> */
    private function acceptedGuarantees(array $guarantee): array
    {
        $value = data_get($guarantee, 'GuaranteesAccepted.GuaranteeAccepted', []);
        if (! is_array($value) || $value === []) {
            return [];
        }

        return array_is_list($value) ? $value : [$value];
    }

    /** @param array<int, array<string, mixed>> $accepted */
    private function accepts(array $accepted, int $code, string $descriptionNeedle): bool
    {
        foreach ($accepted as $item) {
            $itemCode = (int) ($item['GuaranteeTypeCode'] ?? 0);
            $description = strtoupper((string) ($item['GuaranteeTypeDescription'] ?? ''));

            if ($itemCode === $code || ($descriptionNeedle !== '' && str_contains($description, $descriptionNeedle))) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function agencyPaymentAddress(): array
    {
        $city = trim((string) ($this->configuration['agency_city'] ?? 'Lagos'));
        $country = strtoupper(trim((string) ($this->configuration['agency_country_code'] ?? 'NG')));
        $street = trim((string) ($this->configuration['agency_street'] ?? 'Karossy Travels'));

        return array_filter([
            'AddressLine' => [$street !== '' ? $street : 'Karossy Travels'],
            'CityName' => $city !== '' ? $city : 'Lagos',
            'PostCode' => $this->nonBlank('agency_postal_code'),
            'Country' => ['code' => $country !== '' ? $country : 'NG'],
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @return array<string, mixed>|null */
    private function pointOfSale(): ?array
    {
        $iataNumber = $this->iataNumber();
        $pcc = strtoupper(trim((string) ($this->configuration['pcc'] ?? '')));
        $country = strtoupper(trim((string) ($this->configuration['agency_country_code'] ?? 'NG')));
        $street = trim((string) ($this->configuration['agency_street'] ?? 'Karossy Travels'));
        $city = trim((string) ($this->configuration['agency_city'] ?? 'Lagos'));

        if ($iataNumber === '' && $pcc === '') {
            return null;
        }

        $source = array_filter([
            'RequestorID' => $iataNumber !== '' ? [
                'Type' => 5,
                'Id' => $iataNumber,
                'IdContext' => 'IATA',
            ] : null,
            'AgencyAddress' => [
                'AddressLine1' => $street !== '' ? $street : 'Karossy Travels',
                'CityName' => ['content' => $city !== '' ? $city : 'Lagos'],
                'PostalCode' => $this->nonBlank('agency_postal_code'),
                'CountryName' => ['Code' => $country !== '' ? $country : 'NG'],
            ],
            'AgencyName' => trim((string) ($this->configuration['agency_name'] ?? config('app.name', 'Karossy Travels'))),
            'ISOCountryCode' => $country !== '' ? $country : 'NG',
            'PseudoCityCode' => $pcc !== '' ? $pcc : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return ['Source' => $source];
    }

    private function iataNumber(): string
    {
        $value = trim((string) ($this->configuration['iata_number'] ?? ''));

        // Sabre expects the agency IATA identifier here, not a generic agency
        // customer number. IATA numeric identifiers are normally 7-8 digits;
        // reject other values so we never mislabel an ATPCO/agency code as IATA.
        return preg_match('/^[0-9]{7,8}$/', $value) === 1 ? $value : '';
    }

    private function nonBlank(string $key): ?string
    {
        $value = trim((string) ($this->configuration[$key] ?? ''));

        return $value !== '' ? $value : null;
    }

    private function firstNestedValueForKey(mixed $data, string $key): mixed
    {
        if (! is_array($data)) {
            return null;
        }

        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        foreach ($data as $value) {
            $found = $this->firstNestedValueForKey($value, $key);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}

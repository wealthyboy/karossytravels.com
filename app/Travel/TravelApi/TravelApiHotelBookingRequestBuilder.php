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
        $rateInfoRef = ['RateKey' => (string) $offer->rate_key];
        $path = (string) ($this->configuration['hotel_price_check_path'] ?? '');

        // Hotel Price Check v5 accepts RateInfoRef directly. Keep support for a
        // configured v4 endpoint as well so environment overrides remain safe.
        if (str_contains($path, '/v4.0.0/')) {
            return [
                'HotelPriceCheckRQ' => [
                    'version' => '4.0.0',
                    'RateInfoRef' => $rateInfoRef,
                ],
            ];
        }

        return ['RateInfoRef' => $rateInfoRef];
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
     * Validate that Karossy has a supplier-supported way to secure this rate
     * before the customer is charged or an admin attempts the booking.
     *
     * @param array<string, mixed> $priceCheckResponse
     */
    public function assertBookable(array $priceCheckResponse): void
    {
        // Sabre's hotel booking schema requires an IATA RequestorID for GDS
        // hotel content (RateSource 100). Fail before booking rather than send
        // an incomplete request that can never produce a confirmed locator.
        $rateSource = trim((string) ($this->firstNestedValueForKey($priceCheckResponse, 'RateSource') ?? ''));
        $iataNumber = $this->iataNumber();

        if ($rateSource === '100' && $iataNumber === '') {
            throw new RuntimeException('Karossy needs its agency IATA number configured before this GDS hotel rate can be confirmed.');
        }

        $this->paymentInformation($priceCheckResponse);
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

        if (str_contains($rawType, 'PREPAY')) {
            throw new RuntimeException('This hotel rate requires supplier prepayment. Choose a pay-later or agency-guaranteed rate for this checkout.');
        }

        $paymentType = str_contains($rawType, 'DEP') ? 'DEPOSIT' : 'GUARANTEE';
        $accepted = $this->acceptedGuarantees($guarantee);
        $iataNumber = $this->iataNumber();

        if ($paymentType === 'GUARANTEE' && $iataNumber !== '' && ($accepted === [] || $this->accepts($accepted, 19, 'IATA'))) {
            return [
                'Type' => 'GUARANTEE',
                'FormOfPayment' => [
                    'Agency' => ['IATANumber' => $iataNumber],
                ],
            ];
        }

        if ($this->accepts($accepted, 18, 'AGENCY')) {
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

        if ($this->accepts($accepted, 5, 'CREDIT')) {
            throw new RuntimeException('This hotel rate requires a payment-card guarantee from the supplier. Choose a pay-later or agency-guaranteed rate before booking.');
        }

        throw new RuntimeException('This hotel rate requires a supplier form of payment that is not configured for Karossy. Choose another rate or configure an accepted agency guarantee.');
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

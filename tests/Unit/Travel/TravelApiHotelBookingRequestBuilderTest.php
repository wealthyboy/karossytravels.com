<?php

namespace Tests\Unit\Travel;

use App\Models\Customer;
use App\Models\HotelOffer;
use App\Models\HotelSearch;
use App\Travel\TravelApi\TravelApiHotelBookingRequestBuilder;
use RuntimeException;
use Tests\TestCase;

final class TravelApiHotelBookingRequestBuilderTest extends TestCase
{
    public function test_documented_price_check_wraps_the_offer_rate_key(): void
    {
        $builder = new TravelApiHotelBookingRequestBuilder([
            'hotel_price_check_path' => '/v2.1.0/hotel/pricecheck',
        ]);

        $payload = $builder->priceCheck($this->offer());

        $this->assertSame([
            'HotelPriceCheckRQ' => [
                'version' => '2.1.0',
                'RateInfoRef' => ['RateKey' => 'RATE-123'],
            ],
        ], $payload);
    }

    public function test_legacy_v5_price_check_setting_uses_the_documented_contract(): void
    {
        $builder = new TravelApiHotelBookingRequestBuilder([
            'hotel_price_check_path' => '/v5/hotelpricecheck',
        ]);

        $payload = $builder->priceCheck($this->offer());

        $this->assertSame('2.1.0', data_get($payload, 'HotelPriceCheckRQ.version'));
        $this->assertSame('RATE-123', data_get($payload, 'HotelPriceCheckRQ.RateInfoRef.RateKey'));
    }

    public function test_pay_later_booking_omits_payment_information(): void
    {
        $builder = $this->builder();
        $response = $this->priceCheckResponse();

        $builder->assertBookable($response);
        $payload = $builder->booking($this->offer(), $this->customer(), 'BOOK-123', $response);

        $hotelBook = data_get($payload, 'CreatePassengerNameRecordRQ.HotelBook');

        $this->assertSame('BOOK-123', data_get($hotelBook, 'BookingInfo.BookingKey'));
        $this->assertArrayNotHasKey('PaymentInformation', $hotelBook);
        $this->assertTrue((bool) data_get($payload, 'CreatePassengerNameRecordRQ.haltOnHotelBookError'));
        $this->assertTrue((bool) data_get($payload, 'CreatePassengerNameRecordRQ.haltWhenNoHotelCF'));
    }

    public function test_iata_guarantee_is_sent_when_the_supplier_accepts_it(): void
    {
        $builder = $this->builder();
        $response = $this->priceCheckResponse([
            'GuaranteeType' => 'GUAR',
            'GuaranteesAccepted' => [
                'GuaranteeAccepted' => [[
                    'GuaranteeTypeCode' => 19,
                    'GuaranteeTypeDescription' => 'IATA Number',
                ]],
            ],
        ]);

        $builder->assertBookable($response);
        $payload = $builder->booking($this->offer(), $this->customer(), 'BOOK-123', $response);

        $this->assertSame(
            'GUARANTEE',
            data_get($payload, 'CreatePassengerNameRecordRQ.HotelBook.PaymentInformation.Type'),
        );
        $this->assertSame(
            '12345678',
            data_get($payload, 'CreatePassengerNameRecordRQ.HotelBook.PaymentInformation.FormOfPayment.Agency.IATANumber'),
        );
    }

    public function test_credit_card_only_guarantee_fails_instead_of_inventing_card_data(): void
    {
        $builder = $this->builder();
        $response = $this->priceCheckResponse([
            'GuaranteeType' => 'GUAR',
            'GuaranteesAccepted' => [
                'GuaranteeAccepted' => [[
                    'GuaranteeTypeCode' => 5,
                    'GuaranteeTypeDescription' => 'Credit Card',
                ]],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('payment-card guarantee');

        $builder->assertBookable($response);
    }

    public function test_gds_rate_requires_an_iata_number(): void
    {
        $builder = new TravelApiHotelBookingRequestBuilder([
            'hotel_price_check_path' => '/v2.1.0/hotel/pricecheck',
            'pcc' => 'ABCD',
        ]);
        $response = $this->priceCheckResponse();
        data_set($response, 'HotelPriceCheckRS.PriceCheckInfo.HotelRateInfo.RateSource', 100);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('agency IATA');

        $builder->assertBookable($response);
    }

    private function builder(): TravelApiHotelBookingRequestBuilder
    {
        return new TravelApiHotelBookingRequestBuilder([
            'hotel_price_check_path' => '/v2.1.0/hotel/pricecheck',
            'iata_number' => '12345678',
            'pcc' => 'ABCD',
            'agency_name' => 'Karossy Travels',
            'agency_street' => '1 Karossy Way',
            'agency_city' => 'Lagos',
            'agency_postal_code' => '100001',
            'agency_country_code' => 'NG',
        ]);
    }

    private function offer(): HotelOffer
    {
        $search = new HotelSearch([
            'rooms' => 1,
            'adults' => 1,
            'children' => 0,
        ]);

        $offer = new HotelOffer([
            'rate_key' => 'RATE-123',
        ]);
        $offer->setRelation('search', $search);

        return $offer;
    }

    private function customer(): Customer
    {
        return new Customer([
            'first_name' => 'Ada',
            'last_name' => 'Okafor',
            'email' => 'ada@example.test',
            'phone' => '+2348012345678',
        ]);
    }

    /** @param array<string, mixed>|null $guarantee */
    private function priceCheckResponse(?array $guarantee = null): array
    {
        $rateInfo = [
            'RateSource' => 100,
        ];

        if ($guarantee !== null) {
            $rateInfo['Guarantee'] = $guarantee;
        }

        return [
            'HotelPriceCheckRS' => [
                'PriceCheckInfo' => [
                    'BookingKey' => 'BOOK-123',
                    'HotelRateInfo' => $rateInfo,
                ],
            ],
        ];
    }
}

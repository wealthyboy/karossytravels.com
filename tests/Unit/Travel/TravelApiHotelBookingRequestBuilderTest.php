<?php

namespace Tests\Unit\Travel;

use App\Models\Customer;
use App\Models\HotelOffer;
use App\Models\HotelSearch;
use App\Travel\TravelApi\TravelApiHotelBookingRequestBuilder;
use Tests\TestCase;

final class TravelApiHotelBookingRequestBuilderTest extends TestCase
{
    public function test_v5_price_check_preserves_rate_stay_and_occupancy_context(): void
    {
        $builder = new TravelApiHotelBookingRequestBuilder([
            'hotel_price_check_path' => '/v5/hotel/pricecheck',
            'pcc' => 'ABCD',
        ]);

        $payload = $builder->priceCheck($this->offer());

        $this->assertSame('ABCD', data_get($payload, 'HotelPriceCheckRQ.POS.Source.PseudoCityCode'));
        $this->assertSame('RATE-123', data_get($payload, 'HotelPriceCheckRQ.RateInfoRef.RateKey'));
        $this->assertSame('2026-09-10', data_get($payload, 'HotelPriceCheckRQ.RateInfoRef.StayDateTimeRange.StartDate'));
        $this->assertSame('2026-09-11', data_get($payload, 'HotelPriceCheckRQ.RateInfoRef.StayDateTimeRange.EndDate'));
        $this->assertSame(1, data_get($payload, 'HotelPriceCheckRQ.RateInfoRef.Rooms.Room.0.Index'));
        $this->assertSame(1, data_get($payload, 'HotelPriceCheckRQ.RateInfoRef.Rooms.Room.0.Adults'));
        $this->assertSame(0, data_get($payload, 'HotelPriceCheckRQ.RateInfoRef.Rooms.Room.0.Children'));
        $this->assertArrayNotHasKey('version', data_get($payload, 'HotelPriceCheckRQ'));
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

    public function test_credit_card_only_guarantee_is_not_guessed_or_blocked_locally(): void
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

        $builder->assertBookable($response);
        $payload = $builder->booking($this->offer(), $this->customer(), 'BOOK-123', $response);

        $this->assertArrayNotHasKey(
            'PaymentInformation',
            data_get($payload, 'CreatePassengerNameRecordRQ.HotelBook'),
        );
    }

    public function test_gds_rate_without_iata_is_allowed_to_reach_sabre(): void
    {
        $builder = new TravelApiHotelBookingRequestBuilder([
            'hotel_price_check_path' => '/v5/hotel/pricecheck',
            'pcc' => 'ABCD',
        ]);
        $response = $this->priceCheckResponse();
        data_set($response, 'HotelPriceCheckRS.PriceCheckInfo.HotelRateInfo.RateSource', 100);

        $builder->assertBookable($response);
        $payload = $builder->booking($this->offer(), $this->customer(), 'BOOK-123', $response);

        $this->assertSame('BOOK-123', data_get($payload, 'CreatePassengerNameRecordRQ.HotelBook.BookingInfo.BookingKey'));
        $this->assertArrayNotHasKey(
            'PaymentInformation',
            data_get($payload, 'CreatePassengerNameRecordRQ.HotelBook'),
        );
    }

    private function builder(): TravelApiHotelBookingRequestBuilder
    {
        return new TravelApiHotelBookingRequestBuilder([
            'hotel_price_check_path' => '/v5/hotel/pricecheck',
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
            'check_in' => '2026-09-10',
            'check_out' => '2026-09-11',
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

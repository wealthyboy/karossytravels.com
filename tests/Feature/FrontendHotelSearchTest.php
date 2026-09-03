<?php

namespace Tests\Feature;

use App\Models\HotelOffer;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FrontendHotelSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_search_a_complete_hotel_stay_using_check_in_and_check_out_dates(): void
    {
        $checkIn = now()->addDays(14)->startOfDay();
        $checkOut = now()->addDays(18)->startOfDay();

        $criteria = [
            'destination_code' => 'LOS',
            'destination_label' => 'Lagos, Nigeria',
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'adults' => 2,
            'children' => 0,
            'rooms' => 1,
            'currency' => 'NGN',
            'session_id' => (string) Str::uuid(),
        ];

        $response = $this->get(route('hotels.results', $criteria));

        $response->assertOk()
            ->assertSee('Hotels in Lagos, Nigeria')
            ->assertSee($checkIn->format('M j').' – '.$checkOut->format('M j'))
            ->assertSee('Finding the right stays')
            ->assertSee(route('hotels.search.store'), false)
            ->assertSee('2 travellers · 1 room')
            ->assertSee('id="inlineHotelSearch"', false)
            ->assertSee('value="Lagos, Nigeria"', false)
            ->assertSee('value="LOS"', false);

        $this->assertDatabaseCount('hotel_searches', 0);

        $search = $this->postJson(route('hotels.search.store'), $criteria);

        $search->assertOk()
            ->assertJsonPath('meta.property_count', 1)
            ->assertJsonPath('meta.offer_count', 1);
        $this->assertStringContainsString('Karossy Grand Hotel', $search->json('data.html'));
        $this->assertStringContainsString('View rooms', $search->json('data.html'));

        $this->assertDatabaseHas('hotel_searches', [
            'destination_code' => 'LOS',
            'check_in' => $checkIn->toDateTimeString(),
            'check_out' => $checkOut->toDateTimeString(),
            'channel' => 'consumer',
        ]);
        $this->assertDatabaseHas('travel_logs', [
            'product_type' => 'hotel',
            'stage' => 'search',
            'status' => 'success',
        ]);

        $offer = HotelOffer::query()->firstOrFail();
        $this->get(route('hotels.rooms', $offer))
            ->assertOk()
            ->assertSee('Rooms and rates')
            ->assertSee('Deluxe King Room')
            ->assertSee('Best Available Rate')
            ->assertSee('Reserve')
            ->assertSee(route('hotels.checkout', $offer), false);

        $this->get(route('hotels.checkout', $offer))
            ->assertOk()
            ->assertSee('Reserve your room')
            ->assertSee('Paystack secure checkout');

        Mail::fake();
        config()->set('travel.checkout.demo_payment_enabled', true);
        $payment = $this->postJson(route('hotels.checkout.payment', $offer), [
            'first_name' => 'Ada', 'last_name' => 'Okafor',
            'email' => 'ada@example.com', 'phone' => '+234 800 000 0000',
            'special_requests' => 'A quiet room, please.', 'terms' => '1',
        ]);
        $payment->assertCreated()->assertJsonPath('message', 'Your hotel reservation was created.');
        $recordedAmount = Payment::query()->firstOrFail()->amount_minor;
        $this->assertDatabaseHas('orders', ['currency' => 'NGN', 'total_minor' => $recordedAmount]);
        $this->assertDatabaseHas('payments', ['gateway' => 'demo', 'amount_minor' => $recordedAmount]);
    }

    public function test_local_paystack_callback_can_finish_a_hotel_booking_without_a_webhook(): void
    {
        Mail::fake();
        config([
            'services.paystack.public_key' => 'pk_test_checkout',
            'services.paystack.secret_key' => null,
            'travel.checkout.demo_payment_enabled' => false,
            'travel.checkout.local_callback_finalization' => true,
        ]);
        $criteria = [
            'destination_code' => 'LOS', 'destination_label' => 'Lagos, Nigeria',
            'check_in' => now()->addDays(14)->toDateString(), 'check_out' => now()->addDays(18)->toDateString(),
            'adults' => 2, 'children' => 0, 'rooms' => 1, 'currency' => 'NGN', 'session_id' => (string) Str::uuid(),
        ];
        $this->get(route('hotels.results', $criteria))->assertOk();
        $this->postJson(route('hotels.search.store'), $criteria)->assertOk();
        $offer = HotelOffer::query()->firstOrFail();

        $initialize = $this->postJson(route('hotels.checkout.payment', $offer), [
            'first_name' => 'Ada', 'last_name' => 'Okafor', 'email' => 'ada.hotel@example.com',
            'phone_code' => '+234', 'phone' => '8000000000', 'terms' => '1',
        ])->assertOk()->assertJsonPath('public_key', 'pk_test_checkout');

        $this->postJson(route('hotels.checkout.verify', $offer), [
            'reference' => $initialize->json('reference'),
            'transaction_id' => '987654321',
        ])->assertCreated()->assertJsonPath('message', 'Your hotel reservation was created.');

        $this->assertDatabaseHas('payments', [
            'gateway' => 'paystack_callback_local',
            'status' => 'paid',
            'gateway_reference' => $initialize->json('reference'),
        ]);
        $this->assertSame('987654321', (string) Payment::query()->firstOrFail()->metadata['transaction_id']);
    }

    public function test_signed_hotel_webhook_is_reused_by_the_checkout_callback(): void
    {
        Mail::fake();
        Http::preventStrayRequests();
        config([
            'services.paystack.public_key' => 'pk_test_checkout',
            'services.paystack.secret_key' => 'hotel-webhook-secret',
            'travel.checkout.demo_payment_enabled' => false,
            'travel.checkout.local_callback_finalization' => false,
        ]);
        $criteria = [
            'destination_code' => 'LOS', 'destination_label' => 'Lagos, Nigeria',
            'check_in' => now()->addDays(14)->toDateString(), 'check_out' => now()->addDays(18)->toDateString(),
            'adults' => 2, 'children' => 0, 'rooms' => 1, 'currency' => 'NGN', 'session_id' => (string) Str::uuid(),
        ];
        $this->get(route('hotels.results', $criteria))->assertOk();
        $this->postJson(route('hotels.search.store'), $criteria)->assertOk();
        $offer = HotelOffer::query()->firstOrFail();
        $initialize = $this->postJson(route('hotels.checkout.payment', $offer), [
            'first_name' => 'Ada', 'last_name' => 'Okafor', 'email' => 'ada.webhook.hotel@example.com',
            'phone_code' => '+234', 'phone' => '8000000000', 'terms' => '1',
        ])->assertOk();
        $reference = $initialize->json('reference');
        $gatewayData = [
            'status' => 'success', 'amount' => $initialize->json('amount_minor'),
            'currency' => $initialize->json('currency'), 'reference' => $reference,
            'channel' => 'card', 'id' => 246810,
        ];
        $payload = json_encode(['event' => 'charge.success', 'data' => $gatewayData], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha512', $payload, 'hotel-webhook-secret');

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();
        $this->assertDatabaseHas('travel_logs', [
            'product_type' => 'hotel', 'stage' => 'payment_webhook',
            'status' => 'success', 'offer_id' => $offer->id,
        ]);

        $this->postJson(route('hotels.checkout.verify', $offer), ['reference' => $reference])
            ->assertCreated()->assertJsonPath('message', 'Your hotel reservation was created.');
        $this->assertDatabaseHas('payments', ['gateway' => 'paystack', 'gateway_reference' => $reference, 'status' => 'paid']);
    }
}

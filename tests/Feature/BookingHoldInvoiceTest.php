<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingHoldSetting;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class BookingHoldInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ngn_invoice_only_displays_the_ngn_account(): void
    {
        $html = $this->renderInvoice('NGN');

        $this->assertStringContainsString('NGN bank transfer', $html);
        $this->assertStringContainsString('NGN-ACCOUNT-001', $html);
        $this->assertStringNotContainsString('USD-ACCOUNT-002', $html);
    }

    public function test_usd_invoice_only_displays_the_usd_account(): void
    {
        $html = $this->renderInvoice('USD');

        $this->assertStringContainsString('USD bank transfer', $html);
        $this->assertStringContainsString('USD-ACCOUNT-002', $html);
        $this->assertStringNotContainsString('NGN-ACCOUNT-001', $html);
    }

    public function test_an_unsupported_currency_does_not_show_an_unrelated_account(): void
    {
        $html = $this->renderInvoice('GBP');

        $this->assertStringContainsString('GBP bank transfer', $html);
        $this->assertStringNotContainsString('NGN-ACCOUNT-001', $html);
        $this->assertStringNotContainsString('USD-ACCOUNT-002', $html);
    }

    public function test_hold_payment_button_posts_to_a_valid_signed_endpoint(): void
    {
        config(['travel.checkout.demo_payment_enabled' => true]);

        $order = Order::create([
            'reference' => 'KAR-HOLD-BUTTON',
            'channel' => 'mobile',
            'status' => 'pending_payment',
            'payment_mode' => 'hold',
            'currency' => 'NGN',
            'subtotal_minor' => 85573900,
            'total_minor' => 85573900,
            'customer' => ['name' => 'Test Traveller', 'email' => 'traveller@example.com'],
            'payment_due_at' => now()->addDay(),
            'expires_at' => now()->addDay(),
        ]);

        Booking::create([
            'order_id' => $order->id,
            'product_type' => 'flight',
            'provider' => 'travel_api',
            'provider_locator' => 'QQCWYE',
            'status' => 'pending_payment',
            'travellers' => [],
            'details' => ['itinerary' => []],
            'booked_at' => now(),
        ]);

        $order->payments()->create([
            'gateway' => 'bank_transfer',
            'status' => 'pending',
            'currency' => 'NGN',
            'amount_minor' => 85573900,
        ]);

        $pageUrl = URL::signedRoute('checkout.hold.pay', ['order' => $order]);
        $initializeUrl = URL::signedRoute('checkout.hold.pay.initialize', ['order' => $order], absolute: false);

        $this->get($pageUrl)
            ->assertOk()
            ->assertSee(json_encode($initializeUrl), false);

        $this->postJson($initializeUrl)
            ->assertOk()
            ->assertJsonPath('public_key', 'demo')
            ->assertJsonPath('currency', 'NGN');
    }

    private function renderInvoice(string $currency): string
    {
        $order = new Order([
            'reference' => 'KAR-TEST-001',
            'customer' => ['name' => 'Test Traveller', 'email' => 'traveller@example.com'],
            'currency' => $currency,
            'total_minor' => 85573900,
            'payment_due_at' => Carbon::parse('2026-09-23 10:11:50'),
        ]);

        $booking = new Booking([
            'provider_locator' => 'QQCWYE',
            'details' => ['itinerary' => [[
                'origin' => 'LOS',
                'destination' => 'LHR',
                'airline' => 'Qatar Airways',
                'flight_number' => 'QR 1406',
                'departure_at' => '2026-10-05 14:00:00',
                'cabin' => 'economy',
            ]]],
            'travellers' => [[
                'title' => 'Mr',
                'first_name' => 'Test',
                'last_name' => 'Traveller',
                'type' => 'ADT',
            ]],
        ]);

        $settings = new BookingHoldSetting([
            'ngn_bank_name' => 'Naira Bank',
            'ngn_account_name' => 'Karossy NGN',
            'ngn_account_number' => 'NGN-ACCOUNT-001',
            'ngn_sort_code' => 'NGN-SORT',
            'usd_bank_name' => 'Dollar Bank',
            'usd_account_name' => 'Karossy USD',
            'usd_account_number' => 'USD-ACCOUNT-002',
            'usd_sort_code' => 'USD-SWIFT',
        ]);

        return view('mail.booking-invoice', [
            'order' => $order,
            'booking' => $booking,
            'settings' => $settings,
            'paymentUrl' => 'https://example.com/pay/KAR-TEST-001',
        ])->render();
    }
}

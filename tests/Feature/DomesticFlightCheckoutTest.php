<?php

namespace Tests\Feature;

use App\Models\CheckoutPaymentAttempt;
use App\Models\Order;
use App\Models\TravelOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DomesticFlightCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function offer(string $destination = 'ABV'): TravelOffer
    {
        $this->postJson(route('flights.search.store'), [
            'origin' => 'LOS', 'destination' => $destination,
            'departure_date' => now()->addWeek()->toDateString(),
            'trip_type' => 'one_way', 'cabin' => 'economy',
            'adults' => 1, 'children' => 0, 'infants' => 0,
            'currency' => 'NGN', 'session_id' => (string) Str::uuid(),
        ])->assertOk();

        $offer = TravelOffer::query()->latest()->firstOrFail();
        $offer->update(['last_validated_at' => now()]);

        return $offer;
    }

    private function details(): array
    {
        return [
            'travellers' => [[
                'type' => 'ADT', 'title' => 'Mr', 'first_name' => 'Test', 'last_name' => 'Guest',
                'date_of_birth' => '1990-01-01', 'gender' => 'male', 'nationality' => 'NG',
            ]],
            'contact' => ['email' => 'guest@example.com', 'phone_code' => '+234', 'phone' => '8000000000'],
        ];
    }

    public function test_domestic_checkout_hides_all_passport_inputs_and_the_passport_uploader(): void
    {
        $this->get(route('checkout.travellers', $this->offer()))->assertOk()
            ->assertDontSee('name="travellers[0][passport_number]"', false)
            ->assertDontSee('name="travellers[0][passport_expiry]"', false)
            ->assertDontSee('name="travellers[0][passport_country]"', false)
            ->assertDontSee('data-passport-scanner', false)
            ->assertSee('your travel identification')
            ->assertSee('const requiresPassport = false;', false)
            ->assertSee('name="travellers[0][nationality]"', false);
    }

    public function test_domestic_travellers_can_be_saved_without_any_passport_data(): void
    {
        $offer = $this->offer();
        $response = $this->postJson(route('checkout.travellers.store', $offer), $this->details())->assertOk();
        $response->assertSessionHas("flight_checkout.{$offer->id}.travellers", $this->details()['travellers']);
    }

    public function test_domestic_checkout_discards_stale_passport_fields_instead_of_sending_them(): void
    {
        $offer = $this->offer();
        $details = $this->details();
        $details['travellers'][0] += ['passport_number' => '', 'passport_country' => 'NG', 'passport_expiry' => '2000-01-01'];
        $this->postJson(route('checkout.travellers.store', $offer), $details)->assertOk()
            ->assertSessionHas("flight_checkout.{$offer->id}.travellers", $this->details()['travellers']);
    }

    public function test_international_checkout_keeps_the_passport_inputs_and_server_validation(): void
    {
        $offer = $this->offer('LHR');
        $this->get(route('checkout.travellers', $offer))->assertOk()
            ->assertSee('name="travellers[0][passport_number]"', false)
            ->assertSee('name="travellers[0][passport_expiry]"', false)
            ->assertSee('data-passport-scanner', false)
            ->assertSee('const requiresPassport = true;', false);
        $this->postJson(route('checkout.travellers.store', $offer), $this->details())->assertUnprocessable()
            ->assertJsonValidationErrors(['travellers.0.passport_number', 'travellers.0.passport_country', 'travellers.0.passport_expiry']);
    }

    public function test_domestic_checkout_still_requires_valid_names_and_contact_details(): void
    {
        $details = $this->details();
        unset($details['travellers'][0]['first_name'], $details['contact']['email']);
        $this->postJson(route('checkout.travellers.store', $this->offer()), $details)->assertUnprocessable()
            ->assertJsonValidationErrors(['travellers.0.first_name', 'contact.email'])
            ->assertJsonMissingValidationErrors(['travellers.0.passport_number', 'travellers.0.passport_expiry']);
    }

    public function test_domestic_booking_can_complete_after_verified_payment_without_a_passport(): void
    {
        Mail::fake();
        config(['services.paystack.public_key' => 'pk_test_checkout', 'services.paystack.secret_key' => 'test-secret']);
        $offer = $this->offer();
        $this->postJson(route('checkout.travellers.store', $offer), $this->details())->assertOk();
        $this->postJson(route('checkout.payment.initialize', $offer), ['terms' => 1])->assertOk();
        $attempt = CheckoutPaymentAttempt::firstOrFail();
        Http::fake(['https://api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true, 'data' => [
                'status' => 'success', 'amount' => $attempt->amount_minor, 'currency' => $attempt->currency,
                'reference' => $attempt->reference, 'channel' => 'card', 'id' => 987654,
            ],
        ])]);
        $response = $this->postJson(route('checkout.payment.verify', $offer), ['reference' => $attempt->reference])->assertCreated();
        $order = Order::firstOrFail();
        $booking = $order->bookings()->firstOrFail();
        $this->assertArrayNotHasKey('passport_number', $booking->travellers[0]);
        $this->assertNotEmpty($booking->provider_locator);
        $this->assertStringNotContainsString('Passport ending', $response->json('confirmation_html'));
    }
}

<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmation;
use App\Models\Customer;
use App\Models\Addon;
use App\Models\FairRule;
use App\Models\Order;
use App\Models\CheckoutPaymentAttempt;
use App\Models\TravelOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FrontendBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_city_search_opens_the_flight_results_page(): void
    {
        $departure = now()->addWeek()->toDateString();
        $secondDeparture = now()->addWeeks(2)->toDateString();

        $this->get(route('flights.results', [
            'trip_type' => 'multi_city',
            'segments' => [
                ['origin' => 'LOS', 'destination' => 'LHR', 'departure_date' => $departure],
                ['origin' => 'LHR', 'destination' => 'DXB', 'departure_date' => $secondDeparture],
            ],
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'NGN',
            'session_id' => (string) Str::uuid(),
        ]))
            ->assertOk()
            ->assertSee('Choose your flight')
            ->assertSee('Multi-city');
    }

    public function test_invalid_flight_search_returns_to_the_flights_tab(): void
    {
        $this->from(route('home', ['service' => 'hotels']))
            ->get(route('flights.results', ['trip_type' => 'multi_city']))
            ->assertRedirect(route('home', ['service' => 'flights']).'#travel-search');
    }

    public function test_customer_can_create_a_live_provider_booking_and_see_the_pnr(): void
    {
        Mail::fake();
        config([
            'services.paystack.public_key' => 'pk_test_checkout',
            'services.paystack.secret_key' => 'paystack-test-secret',
        ]);
        $user = User::factory()->create([
            'name' => 'Jacob Atam',
            'email' => 'jacob@example.com',
            'account_type' => 'b2c',
            'status' => 'active',
        ]);
        Customer::create([
            'user_id' => $user->id,
            'first_name' => 'Jacob',
            'last_name' => 'Atam',
            'email' => $user->email,
            'phone' => '+234 800 000 0000',
            'status' => 'active',
        ]);
        $this->actingAs($user);

        $departure = now()->addWeek()->toDateString();
        $return = now()->addWeeks(2)->toDateString();
        $criteria = [
            'origin' => 'LOS', 'destination' => 'LHR', 'departure_date' => $departure,
            'return_date' => $return, 'trip_type' => 'round_trip', 'cabin' => 'economy',
            'adults' => 1, 'children' => 0, 'infants' => 0, 'infants_in_seat' => 0,
            'currency' => 'NGN', 'session_id' => (string) Str::uuid(),
        ];

        $this->get(route('flights.results', $criteria))
            ->assertOk()
            ->assertSee('Choose your flight')
            ->assertSee('Finding your best flights');

        $this->assertDatabaseCount('travel_offers', 0);

        $this->postJson(route('flights.search.store'), $criteria)
            ->assertOk()
            ->assertJsonPath('data.offers.0.validating_airline', 'KQ');
        $this->assertDatabaseHas('travel_logs', ['product_type' => 'flight', 'stage' => 'search', 'status' => 'success']);

        $offer = TravelOffer::query()->firstOrFail();
        $baseTotal = $offer->selling_total_minor;
        FairRule::create(['airline_code' => 'KQ', 'title' => 'Kenya Airways changes', 'content' => 'Changes are subject to the airline fare conditions.', 'active' => true]);
        FairRule::create(['airline_code' => 'KAROSSY', 'title' => 'Karossy service rule', 'content' => 'Service fees are non-refundable.', 'is_karossey_rule' => true, 'active' => true]);
        $addon = Addon::create(['type' => 'flight', 'title' => 'Priority assistance', 'description' => 'Assistance before departure.', 'price_cents' => 500000, 'currency' => 'NGN', 'active' => true]);

        $this->get(route('flights.review', $offer))
            ->assertOk()->assertSee('Review your trip')->assertSee('Confirming your flight');

        $this->postJson(route('flights.offers.revalidate', $offer))
            ->assertOk()->assertJsonPath('data.available', true);

        $this->get(route('checkout.travellers', $offer))
            ->assertOk()
            ->assertSee('Traveller details and payment')
            ->assertSee('Finishing your booking')
            ->assertSee('data-booking-finalization-screen', false)
            ->assertSee('data-finalization-progress', false)
            ->assertSee('Passport number')
            ->assertSee('Kenya Airways changes')
            ->assertSee('Karossy service rule')
            ->assertSee('Priority assistance');

        $this->post(route('checkout.travellers.store', $offer), [
            'travellers' => [[
                'type' => 'ADT', 'title' => 'Mr', 'first_name' => 'Jacob', 'last_name' => 'Atam',
                'date_of_birth' => '1990-01-01', 'gender' => 'male', 'nationality' => 'NG',
                'passport_number' => 'A12345678', 'passport_country' => 'NG',
                'passport_expiry' => now()->addYears(2)->toDateString(),
            ]],
            'contact' => ['email' => 'jacob@example.com', 'phone' => '+234 800 000 0000'],
            'notifications' => 1,
        ])->assertRedirect(route('checkout.payment', $offer));

        $this->get(route('checkout.payment', $offer))
            ->assertRedirect(route('checkout.travellers', $offer));

        $initialize = $this->postJson(route('checkout.payment.initialize', $offer), ['terms' => 1, 'addons' => [$addon->id]])
            ->assertOk()
            ->assertJsonPath('public_key', 'pk_test_checkout')
            ->assertJsonPath('metadata.booking_type', 'flight')
            ->assertJsonPath('metadata.traveller_count', 1)
            ->assertJsonMissingPath('access_code');
        $attempt = CheckoutPaymentAttempt::query()->firstOrFail();
        $this->assertDatabaseCount('orders', 0);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => $attempt->amount_minor,
                    'currency' => $attempt->currency,
                    'reference' => $attempt->reference,
                    'channel' => 'card',
                    'id' => 987654,
                ],
            ]),
        ]);
        $response = $this->postJson(route('checkout.payment.verify', $offer), ['reference' => $initialize->json('reference')])
            ->assertCreated()
            ->assertJsonPath('message', 'Your flight was confirmed by the airline.');

        $order = Order::query()->firstOrFail();
        $pnr = $order->bookings()->firstOrFail()->provider_locator;
        $booking = $order->bookings()->firstOrFail();
        $this->assertStringStartsWith('TEST-', $pnr);
        $this->assertSame('website', $booking->source);
        $this->assertSame($baseTotal + 500000, $order->total_minor);
        $this->assertDatabaseHas('booking_addon', ['booking_id' => $booking->id, 'addon_id' => $addon->id, 'price_cents' => 500000, 'currency' => 'NGN']);
        $this->assertDatabaseHas('travel_logs', ['product_type' => 'flight', 'stage' => 'booking', 'status' => 'success']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'gateway' => 'paystack', 'status' => 'paid']);
        $response->assertJsonPath('pnr', $pnr);
        $response->assertJsonPath('reference', $order->reference)
            ->assertJsonStructure(['confirmation_html', 'redirect'])
            ->assertJsonFragment(['message' => 'Your flight was confirmed by the airline.']);
        $this->assertStringContainsString('Your flight is booked', $response->json('confirmation_html'));
        $this->assertStringContainsString($pnr, $response->json('confirmation_html'));
        $this->assertStringContainsString('Jacob Atam', $response->json('confirmation_html'));
        $this->assertStringContainsString('Priority assistance', $response->json('confirmation_html'));

        $this->get(route('checkout.complete', $order))
            ->assertOk()->assertSee('Your flight is booked')->assertSee($pnr)->assertSee($order->reference);
    }

    public function test_guest_can_start_checkout_and_is_invited_to_sign_in_without_leaving(): void
    {
        $offer = $this->createOfferForGuestRedirect();

        $this->get(route('checkout.travellers', $offer))
            ->assertOk()
            ->assertSee('You can still continue as a guest')
            ->assertSee('Sign in or create an account for a better travel experience')
            ->assertSee('Create account');
    }

    public function test_guest_can_create_an_account_inside_checkout_without_a_redirect(): void
    {
        $this->postJson(route('register.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Okafor',
            'email' => 'ada.checkout@example.com',
            'phone' => '+234 801 234 5678',
            'currency_code' => 'NGN',
            'password' => 'Travel1234',
            'password_confirmation' => 'Travel1234',
            'terms' => 1,
        ])->assertCreated()
            ->assertJsonPath('user.name', 'Ada Okafor')
            ->assertJsonStructure(['csrf_token']);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('customers', ['email' => 'ada.checkout@example.com']);
    }

    public function test_local_demo_payment_revalidates_books_logs_and_emails_without_paystack(): void
    {
        Mail::fake();
        config(['travel.checkout.demo_payment_enabled' => true]);
        $offer = $this->createOfferForGuestRedirect();

        $this->postJson(route('checkout.travellers.store', $offer), [
            'travellers' => [[
                'type' => 'ADT', 'title' => 'Ms', 'first_name' => 'Ada', 'last_name' => 'Okafor',
                'date_of_birth' => '1992-05-14', 'gender' => 'female', 'nationality' => 'NG',
                'passport_number' => 'B12345678', 'passport_country' => 'NG',
                'passport_expiry' => now()->addYears(2)->toDateString(),
            ]],
            'contact' => ['email' => 'ada.guest@example.com', 'phone' => '+234 801 234 5678'],
            'notifications' => 1,
        ])->assertOk();

        $response = $this->postJson(route('checkout.payment.initialize', $offer), ['terms' => 1])
            ->assertCreated()
            ->assertJsonPath('message', 'Your flight was confirmed by the airline.')
            ->assertJsonStructure(['pnr', 'reference', 'confirmation_html']);

        $order = Order::query()->firstOrFail();
        $this->assertStringStartsWith('TEST-', (string) $response->json('pnr'));
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'gateway' => 'demo', 'status' => 'simulated']);
        $this->assertDatabaseHas('travel_logs', ['product_type' => 'flight', 'stage' => 'payment', 'status' => 'success', 'order_id' => $order->id]);
        $this->assertDatabaseHas('travel_logs', ['product_type' => 'flight', 'stage' => 'booking', 'status' => 'success', 'order_id' => $order->id]);
        Mail::assertSent(BookingConfirmation::class, fn (BookingConfirmation $mail): bool => $mail->hasTo('ada.guest@example.com'));
    }

    public function test_local_paystack_callback_finishes_booking_when_webhooks_cannot_reach_localhost(): void
    {
        Mail::fake();
        config([
            'app.env' => 'local',
            'services.paystack.public_key' => 'pk_test_checkout',
            'services.paystack.secret_key' => null,
            'travel.checkout.demo_payment_enabled' => false,
            'travel.checkout.local_callback_finalization' => true,
        ]);
        $offer = $this->createOfferForGuestRedirect();

        $this->postJson(route('checkout.travellers.store', $offer), [
            'travellers' => [[
                'type' => 'ADT', 'title' => 'Ms', 'first_name' => 'Ada', 'last_name' => 'Okafor',
                'date_of_birth' => '1992-05-14', 'gender' => 'female', 'nationality' => 'NG',
                'passport_number' => 'B12345678', 'passport_country' => 'NG',
                'passport_expiry' => now()->addYears(2)->toDateString(),
            ]],
            'contact' => ['email' => 'ada.callback@example.com', 'phone' => '+234 801 234 5678'],
            'notifications' => 1,
        ])->assertOk();

        $initialize = $this->postJson(route('checkout.payment.initialize', $offer), ['terms' => 1])
            ->assertOk()
            ->assertJsonPath('public_key', 'pk_test_checkout');

        $response = $this->postJson(route('checkout.payment.verify', $offer), [
            'reference' => $initialize->json('reference'),
            'transaction_id' => '123456789',
        ])->assertCreated()
            ->assertJsonPath('message', 'Your flight was confirmed by the airline.')
            ->assertJsonStructure(['pnr', 'reference', 'confirmation_html', 'redirect']);

        $order = Order::query()->firstOrFail();
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'gateway' => 'paystack_callback_test',
            'status' => 'simulated',
        ]);
        $this->assertDatabaseHas('travel_logs', [
            'product_type' => 'flight',
            'stage' => 'payment',
            'provider' => 'paystack_test_callback',
            'status' => 'success',
            'order_id' => $order->id,
        ]);
        Mail::assertSent(BookingConfirmation::class, fn (BookingConfirmation $mail): bool => $mail->hasTo('ada.callback@example.com'));
        $this->assertStringContainsString('Your flight is booked', $response->json('confirmation_html'));
    }

    public function test_signed_paystack_webhook_records_the_exact_successful_payment_attempt(): void
    {
        config([
            'services.paystack.public_key' => 'pk_test_checkout',
            'services.paystack.secret_key' => 'paystack-webhook-secret',
            'travel.checkout.demo_payment_enabled' => false,
        ]);
        $offer = $this->createOfferForGuestRedirect();
        $this->postJson(route('checkout.travellers.store', $offer), [
            'travellers' => [[
                'type' => 'ADT', 'title' => 'Ms', 'first_name' => 'Ada', 'last_name' => 'Okafor',
                'date_of_birth' => '1992-05-14', 'gender' => 'female', 'nationality' => 'NG',
                'passport_number' => 'B12345678', 'passport_country' => 'NG',
                'passport_expiry' => now()->addYears(2)->toDateString(),
            ]],
            'contact' => ['email' => 'ada.webhook@example.com', 'phone' => '+234 801 234 5678'],
            'notifications' => 1,
        ])->assertOk();
        $this->postJson(route('checkout.payment.initialize', $offer), ['terms' => 1])->assertOk();
        $attempt = CheckoutPaymentAttempt::query()->firstOrFail();
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'status' => 'success',
                'amount' => $attempt->amount_minor,
                'currency' => $attempt->currency,
                'reference' => $attempt->reference,
                'channel' => 'card',
                'id' => 12345,
            ],
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha512', $payload, 'paystack-webhook-secret');

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        ], $payload)
            ->assertOk()
            ->assertJson(['received' => true]);

        $attempt->refresh();
        $this->assertSame('paid', $attempt->status);
        $this->assertNotNull($attempt->verified_at);
        $this->assertDatabaseHas('travel_logs', [
            'product_type' => 'flight',
            'stage' => 'payment_webhook',
            'status' => 'success',
            'offer_id' => $offer->id,
        ]);
    }

    public function test_passenger_names_with_numbers_are_rejected_before_booking(): void
    {
        $user = User::factory()->create([
            'email' => 'invalid-name@example.com',
            'account_type' => 'b2c',
            'status' => 'active',
        ]);
        Customer::create([
            'user_id' => $user->id,
            'first_name' => 'Jacob',
            'last_name' => 'Atam',
            'email' => $user->email,
            'phone' => '+234 800 000 0000',
            'status' => 'active',
        ]);
        $this->actingAs($user);
        $offer = $this->createOfferForGuestRedirect();

        $this->from(route('checkout.travellers', $offer))
            ->post(route('checkout.travellers.store', $offer), [
                'travellers' => [[
                    'type' => 'ADT', 'title' => 'Mr', 'first_name' => 'Jacob', 'last_name' => 'Atam2',
                    'date_of_birth' => '1990-01-01', 'gender' => 'male', 'nationality' => 'NG',
                    'passport_number' => 'A12345678', 'passport_country' => 'NG',
                    'passport_expiry' => now()->addYears(2)->toDateString(),
                ]],
                'contact' => ['email' => $user->email, 'phone' => '+234 800 000 0000'],
            ])
            ->assertRedirect(route('checkout.travellers', $offer))
            ->assertSessionHasErrors('travellers.0.last_name');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_adult_traveller_must_be_at_least_eighteen_at_checkout(): void
    {
        $offer = $this->createOfferForGuestRedirect();

        $this->postJson(route('checkout.travellers.store', $offer), [
            'travellers' => [[
                'type' => 'ADT', 'title' => 'Mr', 'first_name' => 'Young', 'last_name' => 'Traveller',
                'date_of_birth' => now()->subYears(18)->addDay()->toDateString(),
                'gender' => 'male', 'nationality' => 'NG', 'passport_number' => 'A12345678',
                'passport_country' => 'NG', 'passport_expiry' => now()->addYears(2)->toDateString(),
            ]],
            'contact' => ['email' => 'young@example.com', 'phone_code' => '+234', 'phone' => '8000000000'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('travellers.0.date_of_birth');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_home_uses_mobile_product_navigation(): void
    {
        $this->get('/')->assertOk()
            ->assertSeeInOrder(['Hotels', 'Flights', 'Cars', 'Visas', 'Charter']);
    }

    private function createOfferForGuestRedirect(): TravelOffer
    {
        $departure = now()->addWeek()->toDateString();
        $return = now()->addWeeks(2)->toDateString();
        $criteria = [
            'origin' => 'LOS', 'destination' => 'LHR', 'departure_date' => $departure,
            'return_date' => $return, 'trip_type' => 'round_trip', 'cabin' => 'economy',
            'adults' => 1, 'children' => 0, 'infants' => 0, 'infants_in_seat' => 0,
            'currency' => 'NGN', 'session_id' => (string) Str::uuid(),
        ];

        $this->get(route('flights.results', $criteria))->assertOk();
        $this->postJson(route('flights.search.store'), $criteria)->assertOk();

        return TravelOffer::query()->firstOrFail();
    }
}

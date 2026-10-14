<?php

namespace Tests\Feature\Api;

use App\Models\Airline;
use App\Models\CheckoutPaymentAttempt;
use App\Models\CurrencySetting;
use App\Models\FlightDeal;
use App\Models\FlightOffer;
use App\Models\PricingSetting;
use App\Models\TravelOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MobileFlightPromotionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00', 'Africa/Lagos'));
        $this->withHeader('X-Client-Platform', 'mobile');
        CurrencySetting::where('code', 'NGN')->update(['manual_rate' => 1500, 'adjustment_type' => 'none']);
        CurrencySetting::where('code', 'GBP')->update(['manual_rate' => .8, 'adjustment_type' => 'none']);
        Http::fake([
            config('travel.currency.rates_url') => Http::response(['rates' => ['USD' => 1, 'GBP' => .8, 'NGN' => 1500]]),
            'https://api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/fixture', 'access_code' => 'fixture']]),
        ]);
        Http::preventStrayRequests();
        config(['services.paystack.secret_key' => 'test-only', 'services.paystack.base_url' => 'https://api.paystack.co']);
    }

    public function test_bootstrap_and_catalog_use_published_admin_cards_one_featured_airline_and_actual_currency(): void
    {
        $offer = $this->card();
        $bootstrap = $this->getJson('/api/v1/app/bootstrap?currency=NGN')->assertOk()
            ->assertJsonPath('data.homepage.flight_offers.0.id', $offer->id)
            ->assertJsonPath('data.homepage.flight_offers.0.airline_name', 'Qatar Airways')
            ->assertJsonPath('data.homepage.flight_offers.0.price.total_minor', 135000000)
            ->assertJsonPath('data.homepage.flight_offers.0.price.original_total_minor', 150000000)
            ->assertJsonPath('data.homepage.flight_offers.0.price.is_estimate', true)
            ->assertJsonPath('data.homepage.flight_offers.0.promotion.percent', 10)
            ->assertJsonPath('data.homepage.flight_offers.0.search_criteria.departure_date', '2026-10-28');
        $catalog = $this->getJson('/api/v1/flights/promotions?currency=NGN')->assertOk();
        $this->assertSame($bootstrap->json('data.homepage.flight_offers'), $catalog->json('data.offers'));
        $this->assertSame(['QR', 'TK'], $catalog->json('data.offers.0.promotion.eligible_airline_codes'));
        $this->assertStringNotContainsString('Turkish Airlines', $catalog->getContent());
        $this->assertStringNotContainsString('priority', $catalog->getContent());
        $this->assertStringNotContainsString('provider_reference', $catalog->getContent());
        $this->getJson('/api/v1/flights/promotions?currency=GBP')->assertOk()
            ->assertJsonPath('data.offers.0.price.currency', 'GBP')->assertJsonPath('data.offers.0.price.total_minor', 72000);
    }

    public function test_catalog_omits_drafts_past_departures_and_returns_at_most_four(): void
    {
        $offer = $this->card();
        for ($i = 1; $i <= 5; $i++) $offer->replicate()->fill(['slug' => 'card-'.$i, 'flight_deal_id' => null, 'sort_order' => $i])->save();
        $offer->replicate()->fill(['slug' => 'draft', 'active' => false])->save();
        $offer->replicate()->fill(['slug' => 'past', 'departure_date' => today()->subDay()])->save();
        $this->getJson('/api/v1/flights/promotions?currency=USD')->assertOk()->assertJsonCount(4, 'data.offers');
    }

    public function test_expired_discount_disappears_but_card_keeps_unreduced_estimate(): void
    {
        $offer = $this->card();
        $offer->deal->update(['booking_until' => '2026-10-13']);
        $this->getJson('/api/v1/flights/promotions?currency=USD')->assertOk()
            ->assertJsonPath('data.offers.0.promotion', null)
            ->assertJsonPath('data.offers.0.price.discount_minor', 0)
            ->assertJsonPath('data.offers.0.price.total_minor', 100000);
    }

    public function test_live_search_review_and_mobile_payment_return_the_same_discount_breakdown(): void
    {
        $deal = $this->deal(['KQ']);
        PricingSetting::where('product_type', 'airline')->update(['enabled' => true, 'markup_type' => 'percentage', 'markup_value' => 5, 'adjustment_type' => 'markdown']);
        $search = $this->postJson('/api/v1/flights/search', $this->criteria())->assertOk()
            ->assertJsonPath('data.offers.0.price.total_minor', 44887500)
            ->assertJsonPath('data.offers.0.price.promotion_discount_minor', 4987500)
            ->assertJsonPath('data.offers.0.price.markdown_minor', 2625000)
            ->assertJsonPath('data.offers.0.promotion.percent', 10);
        $id = $search->json('data.offers.0.id');
        $review = $this->getJson('/api/v1/flights/offers/'.$id.'?currency=NGN')->assertOk();
        $this->assertSame($search->json('data.offers.0.price.total_minor'), $review->json('data.offer.price.total_minor'));
        $this->assertSame($search->json('data.offers.0.price.discount_minor'), $review->json('data.offer.price.discount_minor'));
        $this->assertSame($deal->id, $review->json('data.offer.promotion.id'));
        $payment = $this->postJson('/api/v1/flights/offers/'.$id.'/checkout', $this->checkout(['expected_total_minor' => 44887500]))
            ->assertCreated()->assertJsonPath('data.price.total_minor', 44887500)->assertJsonPath('data.promotion.percent', 10);
        $this->assertSame($review->json('data.offer.price'), $payment->json('data.price'));
        $this->assertSame(44887500, (int) CheckoutPaymentAttempt::firstOrFail()->amount_minor);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transaction/initialize') && $request['amount'] === '44887500' && $request['currency'] === 'NGN');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_changed_or_expired_promotion_returns_fresh_fare_and_never_starts_payment(): void
    {
        $deal = $this->deal(['KQ']);
        $search = $this->postJson('/api/v1/flights/search', $this->criteria())->assertOk();
        $deal->update(['active' => false]);
        $this->postJson('/api/v1/flights/offers/'.$search->json('data.offers.0.id').'/checkout', $this->checkout(['expected_total_minor' => 47250000]))
            ->assertConflict()->assertJsonPath('error_code', 'FARE_CHANGED')->assertJsonPath('data.offer.price.total_minor', 52500000)
            ->assertJsonPath('data.offer.promotion', null);
        $this->assertDatabaseCount('checkout_payment_attempts', 0);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'paystack'));
    }

    public function test_stale_client_total_is_rejected_even_if_stored_fare_was_already_revalidated(): void
    {
        $this->deal(['KQ']);
        $search = $this->postJson('/api/v1/flights/search', $this->criteria())->assertOk();
        $this->postJson('/api/v1/flights/offers/'.$search->json('data.offers.0.id').'/checkout', $this->checkout(['expected_total_minor' => 1]))
            ->assertConflict()->assertJsonPath('data.offer.price.total_minor', 47250000);
        $this->assertDatabaseCount('checkout_payment_attempts', 0);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'paystack'));
    }

    public function test_client_cannot_set_discount_and_supported_gbp_currency_matches_charge(): void
    {
        $this->deal(['KQ']);
        $search = $this->postJson('/api/v1/flights/search', $this->criteria())->assertOk();
        $id = $search->json('data.offers.0.id');
        $this->getJson('/api/v1/flights/offers/'.$id.'?currency=GBP')->assertOk()->assertJsonPath('data.offer.price.currency', 'GBP');
        $response = $this->postJson('/api/v1/flights/offers/'.$id.'/checkout', $this->checkout(['currency' => 'GBP', 'discount_percent' => 99, 'total_minor' => 1]))->assertCreated();
        $this->assertSame('GBP', $response->json('data.price.currency'));
        $this->assertSame(25200, $response->json('data.price.total_minor'));
        $this->assertSame(25200, (int) CheckoutPaymentAttempt::firstOrFail()->amount_minor);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transaction/initialize') && $request['amount'] === '25200' && $request['currency'] === 'GBP');
    }

    private function card(): FlightOffer
    {
        return FlightOffer::create(['slug' => 'los-london', 'origin_airport' => 'LOS', 'origin_city' => 'Lagos', 'destination_airport' => 'LHR', 'destination_city' => 'London', 'airline_name' => 'Qatar Airways', 'airline_code' => 'QR', 'departure_date' => '2026-10-28', 'return_date' => '2026-11-04', 'cabin' => 'economy', 'price_minor' => 100000, 'currency' => 'USD', 'label' => 'TEST', 'active' => true, 'promotion_enabled' => true, 'flight_deal_id' => $this->deal(['QR', 'TK'])->id]);
    }

    private function deal(array $airlines): FlightDeal
    {
        return FlightDeal::create(['airline_id' => Airline::where('code', $airlines[0])->firstOrFail()->id, 'airline_codes' => $airlines, 'origin_airport' => 'LOS', 'destination_airport' => 'LHR', 'booking_from' => '2026-10-14', 'booking_until' => '2026-10-18', 'discount_type' => 'percentage', 'discount_value' => 10, 'active' => true]);
    }

    private function criteria(): array
    {
        return ['origin' => 'LOS', 'destination' => 'LHR', 'departure_date' => '2026-10-28', 'return_date' => '2026-11-04', 'trip_type' => 'round_trip', 'cabin' => 'economy', 'adults' => 1, 'children' => 0, 'children_ages' => [], 'currency' => 'NGN', 'session_id' => (string) Str::uuid()];
    }

    private function checkout(array $changes = []): array
    {
        return ['currency' => 'NGN', 'terms' => true, 'travellers' => [['type' => 'ADT', 'title' => 'Mr', 'first_name' => 'Test', 'last_name' => 'Guest', 'date_of_birth' => '1990-01-01', 'gender' => 'male', 'nationality' => 'NG', 'passport_number' => 'TEST12345', 'passport_country' => 'NG', 'passport_expiry' => '2030-01-01']], 'contact' => ['email' => 'mobile@example.com', 'phone_code' => '+234', 'phone' => '8000000000'], ...$changes];
    }
}

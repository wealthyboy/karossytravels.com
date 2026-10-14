<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\CheckoutPaymentAttempt;
use App\Models\CurrencySetting;
use App\Models\FlightDeal;
use App\Models\FlightOffer;
use App\Models\Permission;
use App\Models\PricingSetting;
use App\Models\Role;
use App\Models\TravelOffer;
use App\Models\User;
use App\Travel\Data\FlightOffer as ProviderOffer;
use App\Travel\FlightRevalidationService;
use App\Travel\Pricing\FlightDealMatcher;
use App\Travel\Pricing\OfferPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FlightPromotionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00', 'Africa/Lagos'));
        PricingSetting::where('product_type', 'airline')->update(['enabled' => false]);
        CurrencySetting::where('code', 'NGN')->update(['manual_rate' => 1500, 'adjustment_type' => 'none']);
        Http::preventStrayRequests();
    }

    public function test_admin_creates_one_card_and_one_live_rule_for_multiple_airlines(): void
    {
        $this->actingAs($this->admin())->post(route('admin.flight-offers.store'), $this->offerInput())
            ->assertSessionHasNoErrors()->assertRedirect();
        $offer = FlightOffer::firstOrFail();
        $this->assertSame(['QR', 'TK'], $offer->deal->airline_codes);
        $this->assertSame('LOS', $offer->deal->origin_airport);
        $this->assertSame('LHR', $offer->deal->destination_airport);
        $this->assertTrue($offer->deal->active);
        $this->assertDatabaseCount('flight_deals', 1);
        $this->assertSame(1000, $this->discount('QR')['discount_minor']);
        $this->assertSame(1000, $this->discount('TK')['discount_minor']);

        $this->get('/?currency=USD')->assertOk()->assertSee('Qatar Airways')->assertDontSee('Turkish Airlines')
            ->assertSee('$900')->assertSee('homepage-flight-offer-image')->assertSee('View live flights');
    }

    public function test_selected_airlines_route_direction_and_departure_boundaries_match_exactly(): void
    {
        $this->deal(['travel_from' => '2026-10-20', 'travel_until' => '2026-10-25']);
        foreach (['QR', 'TK'] as $code) {
            foreach (['2026-10-20', '2026-10-25'] as $date) {
                $this->assertSame(1000, $this->discount($code, $date)['discount_minor']);
            }
        }
        foreach ([['BA', '2026-10-22', 'LOS', 'LHR'], ['QR', '2026-10-19', 'LOS', 'LHR'], ['QR', '2026-10-26', 'LOS', 'LHR'], ['QR', '2026-10-22', 'LHR', 'LOS'], ['QR', '2026-10-22', 'LOS', 'LGW'], ['QR', '2026-10-22', 'ABV', 'LHR']] as [$code, $date, $from, $to]) {
            $this->assertSame(0, $this->discount($code, $date, $from, $to)['discount_minor']);
        }
    }

    public function test_all_airlines_includes_carriers_not_in_the_local_list(): void
    {
        $this->deal(['all_airlines' => true, 'airline_id' => null, 'airline_codes' => []]);
        foreach (['QR', 'TK', 'BA', 'XX'] as $code) {
            $this->assertSame(1000, $this->discount($code)['discount_minor']);
        }
        $this->assertSame(0, $this->discount('XX', origin: 'ABV')['discount_minor']);
    }

    public function test_booking_window_is_inclusive_and_uses_lagos_not_utc(): void
    {
        $this->deal(['booking_from' => '2026-10-14', 'booking_until' => '2026-10-18']);
        $this->travelTo(Carbon::parse('2026-10-13 23:30:00', 'UTC'));
        $this->assertSame(1000, $this->discount('QR')['discount_minor']);
        $this->travelTo(Carbon::parse('2026-10-18 22:59:59', 'UTC'));
        $this->assertSame(1000, $this->discount('QR')['discount_minor']);
        $this->travelTo(Carbon::parse('2026-10-18 23:00:00', 'UTC'));
        $this->assertSame(0, $this->discount('QR')['discount_minor']);
    }

    public function test_both_windows_must_match_and_return_date_is_not_the_departure_window(): void
    {
        $deal = $this->deal(['booking_from' => '2026-10-14', 'booking_until' => '2026-10-18', 'travel_from' => '2026-10-20', 'travel_until' => '2026-10-25']);
        $this->assertSame(1000, $this->discount('QR')['discount_minor']);
        $this->assertSame(0, $this->discount('QR', '2026-10-26')['discount_minor']);
        $deal->update(['booking_from' => '2026-10-15']);
        $this->assertSame(0, $this->discount('QR')['discount_minor']);
    }

    public function test_inactive_rules_and_inactive_selected_airlines_do_not_discount(): void
    {
        $deal = $this->deal(['active' => false]);
        $this->assertSame(0, $this->discount('QR')['discount_minor']);
        $deal->update(['active' => true]);
        Airline::where('code', 'QR')->update(['active' => false]);
        $this->assertSame(0, $this->discount('QR')['discount_minor']);
        $this->assertSame(1000, $this->discount('TK')['discount_minor']);
    }

    public function test_overlapping_promotions_do_not_stack_and_priority_wins_then_greatest_saving(): void
    {
        $first = $this->deal(['discount_value' => 10]);
        $best = $this->deal(['discount_value' => 20]);
        $this->assertSame(['deal_id' => $best->id, 'discount_minor' => 2000], $this->discount('QR'));
        $first->update(['priority' => 1]);
        $this->assertSame(['deal_id' => $first->id, 'discount_minor' => 1000], $this->discount('QR'));
    }

    public function test_legacy_single_airline_and_fixed_rules_still_price_correctly(): void
    {
        $legacy = $this->deal(['airline_codes' => null, 'discount_type' => 'fixed', 'discount_value' => 5, 'discount_currency' => 'USD']);
        $this->deal(['discount_value' => 10]);
        $this->assertSame(1000, $this->discount('QR')['discount_minor']);
        $legacy->update(['priority' => 2]);
        $this->assertSame(500, $this->discount('QR')['discount_minor']);
        $this->assertSame(1000, $this->discount('TK')['discount_minor']);
    }

    public function test_airport_wildcards_apply_independently(): void
    {
        $this->deal(['origin_airport' => null]);
        $this->assertSame(1000, $this->discount('QR', origin: 'ABV')['discount_minor']);
        $this->assertSame(0, $this->discount('QR', destination: 'LGW')['discount_minor']);
    }

    public function test_update_changes_the_same_rule_and_all_airlines_clears_the_selected_list(): void
    {
        $this->actingAs($this->admin())->post(route('admin.flight-offers.store'), $this->offerInput())->assertSessionHasNoErrors();
        $offer = FlightOffer::firstOrFail();
        $dealId = $offer->flight_deal_id;
        $this->put(route('admin.flight-offers.update', $offer), [...$this->offerInput(), 'airline_scope' => 'all', 'discount_percent' => 15])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('flight_deals', 1);
        $this->assertSame($dealId, $offer->fresh()->flight_deal_id);
        $this->assertTrue($offer->fresh()->deal->all_airlines);
        $this->assertSame([], $offer->fresh()->deal->airline_codes);
        $this->assertSame(1500, $this->discount('BA')['discount_minor']);
        $this->get(route('admin.flight-deals.index'))->assertOk()->assertSee('All airlines');
        $this->get(route('admin.flight-offers.edit', $offer))->assertOk()->assertSee('Featured airline')->assertSee('Promotion starts');
    }

    public function test_draft_disabled_and_deleted_cards_cannot_leave_a_live_discount_running(): void
    {
        $this->actingAs($this->admin())->post(route('admin.flight-offers.store'), [...$this->offerInput(), 'active' => 0])->assertSessionHasNoErrors();
        $offer = FlightOffer::firstOrFail();
        $this->assertSame(0, $this->discount('QR')['discount_minor']);
        $this->put(route('admin.flight-offers.update', $offer), $this->offerInput())->assertSessionHasNoErrors();
        $this->assertSame(1000, $this->discount('QR')['discount_minor']);
        $this->put(route('admin.flight-offers.update', $offer), [...$this->offerInput(), 'promotion_enabled' => 0])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->discount('QR')['discount_minor']);
        $this->put(route('admin.flight-offers.update', $offer), $this->offerInput())->assertSessionHasNoErrors();
        $this->delete(route('admin.flight-offers.destroy', $offer))->assertRedirect();
        $this->assertSame(0, $this->discount('QR')['discount_minor']);
    }

    public function test_bad_discount_inputs_are_rejected_before_any_offer_or_rule_is_saved(): void
    {
        $this->actingAs($this->admin());
        foreach ([
            [['discount_percent' => 100], 'discount_percent'],
            [['discount_percent' => -1], 'discount_percent'],
            [['airline_ids' => []], 'airline_ids'],
            [['airline_ids' => [99999]], 'airline_ids.0'],
            [['booking_until' => '2026-10-13'], 'booking_until'],
            [['booking_until' => null], 'booking_until'],
            [['booking_from' => null, 'booking_until' => null], 'travel_from'],
            [['travel_from' => '2026-11-01', 'travel_until' => '2026-10-30'], 'travel_until'],
            [['airline_code' => 'BA'], 'airline_code'],
        ] as [$changes, $error]) {
            $this->postJson(route('admin.flight-offers.store'), [...$this->offerInput(), ...$changes])->assertUnprocessable()->assertJsonValidationErrors($error);
        }
        $this->assertDatabaseCount('flight_deals', 0);
        $this->assertDatabaseCount('flight_offers', 0);
    }

    public function test_standalone_deals_support_all_airlines_and_booking_only_windows(): void
    {
        $this->actingAs($this->admin())->post(route('admin.flight-deals.store'), [
            'airline_scope' => 'all', 'discount_percent' => 10, 'booking_from' => '2026-10-14', 'booking_until' => '2026-10-18',
            'origin_airport' => 'los', 'destination_airport' => 'lhr', 'active' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('flight_deals', ['all_airlines' => true, 'origin_airport' => 'LOS', 'destination_airport' => 'LHR', 'travel_from' => null]);
        $this->assertSame(1000, $this->discount('QR')['discount_minor']);
        $this->get(route('admin.flight-deals.edit', FlightDeal::firstOrFail()))->assertOk()->assertSee('All airlines');
    }

    public function test_unauthorized_customer_cannot_create_or_update_promotions(): void
    {
        $this->actingAs(User::factory()->create(['account_type' => 'b2c']))
            ->postJson(route('admin.flight-offers.store'), $this->offerInput())->assertForbidden();
        $this->assertDatabaseCount('flight_deals', 0);
    }

    public function test_discount_applies_after_normal_markup_and_before_currency_conversion(): void
    {
        $this->deal();
        PricingSetting::where('product_type', 'airline')->update(['enabled' => true, 'adjustment_type' => 'markup', 'markup_type' => 'percentage', 'markup_value' => 5]);
        $priced = app(OfferPricingService::class)->price(new ProviderOffer('test', 'QR', [['origin' => 'LOS', 'destination' => 'LHR', 'departure_at' => '2026-10-22T09:00:00+01:00']], 'USD', 8000, 2000, 10000, false), 'consumer', 'NGN');
        $this->assertSame(500, $priced['markup_minor']);
        $this->assertSame(1050, $priced['deal_discount_minor']);
        $this->assertSame(9450, $priced['selling_total_minor']);
        $this->assertSame(14175000, $priced['display_total_minor']);
    }

    public function test_round_trip_search_revalidation_review_and_payment_keep_one_discount(): void
    {
        $deal = $this->deal(['airline_id' => Airline::where('code', 'KQ')->firstOrFail()->id, 'airline_codes' => ['KQ']]);
        $response = $this->postJson('/api/v1/flights/search', $this->criteria())->assertOk()
            ->assertJsonPath('data.offers.0.price.total_minor', 47250000)
            ->assertJsonPath('data.offers.0.price.discount_minor', 5250000);
        $offer = TravelOffer::findOrFail($response->json('data.offers.0.id'));
        $this->assertSame($deal->id, $offer->flight_deal_id);
        $this->get(route('flights.review', $offer))->assertOk()->assertSee('472,500.00');
        foreach ([1, 2] as $unused) {
            app(FlightRevalidationService::class)->revalidate($offer->fresh());
            $this->assertSame(47250000, (int) $offer->fresh()->selling_total_minor);
        }
        config(['services.paystack.public_key' => 'pk_test_promotions', 'services.paystack.secret_key' => null]);
        $this->post(route('checkout.travellers.store', $offer), [
            'travellers' => [['type' => 'ADT', 'title' => 'Mr', 'first_name' => 'Test', 'last_name' => 'Guest', 'date_of_birth' => '1990-01-01', 'gender' => 'male', 'nationality' => 'NG', 'passport_number' => 'TEST12345', 'passport_country' => 'NG', 'passport_expiry' => '2030-01-01']],
            'contact' => ['email' => 'guest@example.com', 'phone' => '+2348000000000'],
        ])->assertSessionHasNoErrors();
        $this->postJson(route('checkout.payment.initialize', $offer), ['terms' => 1])->assertOk();
        $this->assertSame(47250000, (int) CheckoutPaymentAttempt::firstOrFail()->amount_minor);
        Http::assertNothingSent();
    }

    public function test_expired_promotion_is_removed_on_revalidation_and_customer_is_told_price_changed(): void
    {
        $deal = $this->deal(['all_airlines' => true, 'airline_codes' => [], 'booking_from' => '2026-10-14', 'booking_until' => '2026-10-18']);
        $response = $this->postJson('/api/v1/flights/search', $this->criteria())->assertOk();
        $offer = TravelOffer::findOrFail($response->json('data.offers.0.id'));
        $deal->update(['booking_until' => '2026-10-13']);
        $result = app(FlightRevalidationService::class)->revalidate($offer);
        $this->assertTrue($result['price_changed']);
        $this->assertSame(52500000, $result['total_minor']);
        $this->assertNull($offer->fresh()->flight_deal_id);
    }

    private function deal(array $changes = []): FlightDeal
    {
        return FlightDeal::create([
            'airline_id' => Airline::where('code', 'QR')->firstOrFail()->id, 'airline_codes' => ['QR', 'TK'], 'all_airlines' => false,
            'origin_airport' => 'LOS', 'destination_airport' => 'LHR', 'travel_from' => null, 'travel_until' => null,
            'booking_from' => '2026-10-14', 'booking_until' => '2026-10-18', 'discount_type' => 'percentage', 'discount_value' => 10,
            'active' => true, ...$changes,
        ]);
    }

    private function discount(string $airline, string $date = '2026-10-22', string $origin = 'LOS', string $destination = 'LHR'): array
    {
        return app(FlightDealMatcher::class)->discount($airline, [], 10000, 'USD', $date, $origin, $destination);
    }

    private function offerInput(): array
    {
        return [
            'origin_airport' => 'LOS', 'origin_city' => 'Lagos', 'destination_airport' => 'LHR', 'destination_city' => 'London',
            'airline_name' => 'Qatar Airways', 'airline_code' => 'QR', 'departure_date' => '2026-10-22', 'return_date' => '2026-10-29',
            'cabin' => 'economy', 'price' => 1000, 'currency' => 'USD', 'active' => 1, 'promotion_enabled' => 1,
            'airline_scope' => 'selected', 'airline_ids' => Airline::whereIn('code', ['QR', 'TK'])->orderBy('id')->pluck('id')->all(),
            'discount_percent' => 10, 'booking_from' => '2026-10-14', 'booking_until' => '2026-10-18',
        ];
    }

    private function criteria(): array
    {
        return ['origin' => 'LOS', 'destination' => 'LHR', 'departure_date' => '2026-10-22', 'return_date' => '2026-10-29', 'trip_type' => 'round_trip', 'cabin' => 'economy', 'adults' => 1, 'currency' => 'NGN', 'session_id' => (string) Str::uuid()];
    }

    private function admin(): User
    {
        $user = User::factory()->create(['account_type' => 'admin', 'currency_code' => 'USD']);
        $role = Role::create(['name' => 'test-promotion-admin', 'label' => 'Promotion admin']);
        $role->permissions()->attach(Permission::create(['name' => 'offers.manage', 'label' => 'Manage offers']));
        $user->roles()->attach($role);

        return $user;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\CheckoutPaymentAttempt;
use App\Models\CurrencySetting;
use App\Models\FlightDeal;
use App\Models\HotelOffer;
use App\Models\Permission;
use App\Models\PricingSetting;
use App\Models\Role;
use App\Models\TravelOffer;
use App\Models\User;
use App\Travel\Data\FlightOffer;
use App\Travel\FlightRevalidationService;
use App\Travel\Pricing\HotelPricingService;
use App\Travel\Pricing\OfferPricingService;
use App\Travel\Pricing\PercentagePriceAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PercentagePricingAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['rates' => ['USD' => 1, 'NGN' => 1500, 'GBP' => .8, 'EUR' => .9]])]);
        Cache::put('travel:exchange-rates:USD', ['NGN' => 1500, 'GBP' => .8, 'EUR' => .9]);
        CurrencySetting::where('code', 'NGN')->update(['manual_rate' => 1500, 'adjustment_type' => 'none']);
    }

    #[DataProvider('directionsAndCurrencies')]
    public function test_flight_percentages_apply_to_each_price_before_display_conversion(string $direction, string $currency, float $rate): void
    {
        $this->rule('airline', $direction, 10);
        foreach ([10000, 27500] as $base) {
            $price = app(OfferPricingService::class)->price($this->flight($base), 'consumer', $currency);
            $adjusted = (int) ($base * ($direction === 'markup' ? 1.1 : .9));
            $this->assertSame($base, $price['provider_total_minor']);
            $this->assertSame($adjusted, $price['selling_total_minor']);
            $this->assertSame((int) round($adjusted * $rate), $price['display_total_minor']);
            $this->assertSame($direction, $price['adjustment_type']);
        }
    }

    public static function directionsAndCurrencies(): array
    {
        $cases = [];
        foreach (['markup', 'markdown'] as $direction) {
            foreach (['USD' => 1.0, 'NGN' => 1500.0, 'GBP' => .8, 'EUR' => .9] as $currency => $rate) {
                $cases["$direction in $currency"] = [$direction, $currency, $rate];
            }
        }

        return $cases;
    }

    #[DataProvider('directionsAndCurrencies')]
    public function test_hotel_total_and_nightly_prices_use_the_same_percentage(string $direction, string $currency, float $rate): void
    {
        $this->rule('hotel', $direction, 10);
        $price = app(HotelPricingService::class)->price(['total_minor' => 42000, 'nightly_minor' => 21000, 'currency' => 'USD'], $currency, 2);
        $selling = $direction === 'markup' ? 46200 : 37800;
        $this->assertSame($selling, $price['selling_total_minor']);
        $this->assertSame((int) round($selling * $rate), $price['display_total_minor']);
        $this->assertSame((int) round($price['display_total_minor'] / 2), $price['display_nightly_minor']);
        $this->assertSame($direction === 'markup' ? 4200 : 0, $price['markup_minor']);
        $this->assertSame($direction === 'markdown' ? 4200 : 0, $price['markdown_minor']);
    }

    public function test_blank_zero_disabled_and_legacy_fixed_rules_leave_prices_unchanged(): void
    {
        config(['travel.pricing.markup_basis_points.consumer' => 2500]);
        foreach (['airline', 'hotel'] as $product) {
            foreach ([['markup_value' => null], ['markup_value' => 0], ['enabled' => false], ['markup_type' => 'fixed', 'markup_value' => 10000]] as $override) {
                $setting = $this->rule($product, 'markup', 10);
                $setting->update($override);
                $price = $product === 'airline'
                    ? app(OfferPricingService::class)->price($this->flight(10000), 'consumer', 'USD')
                    : app(HotelPricingService::class)->price(['total_minor' => 10000, 'nightly_minor' => 10000, 'currency' => 'USD'], 'USD', 1);
                $this->assertSame(10000, $price['selling_total_minor']);
                $this->assertSame(0, $price['markup_minor']);
                $this->assertSame(0, $price['markdown_minor']);
            }
        }
        Http::assertNothingSent();
    }

    public function test_old_fixed_currency_is_ignored_for_percentage_calculation(): void
    {
        $this->rule('airline', 'markup', 10)->update(['currency' => 'NGN']);
        $this->assertSame(11000, app(OfferPricingService::class)->price($this->flight(10000), 'consumer', 'USD')['selling_total_minor']);
        $this->rule('hotel', 'markdown', 10)->update(['currency' => 'NGN']);
        $this->assertSame(9000, app(HotelPricingService::class)->price(['total_minor' => 10000, 'nightly_minor' => 10000, 'currency' => 'USD'], 'USD')['selling_total_minor']);
        Http::assertNothingSent();
    }

    public function test_rounding_keeps_a_markdown_below_the_supplier_total(): void
    {
        $setting = $this->rule('airline', 'markdown', 99.99);
        $this->assertSame(1, app(PercentagePriceAdjustment::class)->calculate(1, $setting)['total_minor']);
        $this->assertSame(1, app(PercentagePriceAdjustment::class)->calculate(10000, $setting)['total_minor']);
        foreach ([100, 150, -1] as $invalid) {
            $setting->update(['markup_value' => $invalid]);
            $this->assertSame(10000, app(PercentagePriceAdjustment::class)->calculate(10000, $setting)['total_minor']);
        }
    }

    public function test_flight_deals_apply_after_the_default_markdown_without_being_confused_with_it(): void
    {
        $this->rule('airline', 'markdown', 10);
        FlightDeal::create(['airline_id' => Airline::where('code', 'KQ')->firstOrFail()->id, 'travel_from' => today(), 'travel_until' => today()->addMonth(), 'discount_type' => 'percentage', 'discount_value' => 5, 'active' => true]);
        $price = app(OfferPricingService::class)->price($this->flight(10000), 'consumer', 'USD');
        $this->assertSame(1000, $price['markdown_minor']);
        $this->assertSame(450, $price['deal_discount_minor']);
        $this->assertSame(1450, $price['display_discount_minor']);
        $this->assertSame(8550, $price['selling_total_minor']);
    }

    public function test_admin_forms_offer_only_percentage_markup_and_markdown_for_both_products(): void
    {
        $this->actingAs($this->admin());
        foreach (['airline', 'hotel'] as $product) {
            $this->get(route('admin.pricing.edit', $product))->assertOk()
                ->assertSee('Markup — increase the price')->assertSee('Markdown — decrease the price')
                ->assertSee('Adjustment percentage')->assertSee('data-pricing-example', false)
                ->assertDontSee('Fixed-markup currency')->assertDontSee('<option value="fixed"', false);
        }
    }

    public function test_admin_can_save_both_directions_without_selecting_a_currency(): void
    {
        $this->actingAs($this->admin());
        foreach (['airline', 'hotel'] as $product) {
            foreach (['markup', 'markdown'] as $direction) {
                $this->put(route('admin.pricing.update', $product), ['adjustment_type' => $direction, 'markup_value' => 7.5, 'enabled' => 1])->assertSessionHasNoErrors();
                $this->assertDatabaseHas('pricing_settings', ['product_type' => $product, 'adjustment_type' => $direction, 'markup_type' => 'percentage', 'markup_value' => 7.5, 'enabled' => true]);
            }
        }
        $this->assertDatabaseCount('audit_logs', 4);
    }

    public function test_admin_can_clear_or_disable_rules(): void
    {
        $this->actingAs($this->admin());
        foreach (['airline', 'hotel'] as $product) {
            $this->rule($product, 'markup', 10);
            $this->put(route('admin.pricing.update', $product), ['adjustment_type' => 'markdown', 'markup_value' => '', 'enabled' => 1])->assertSessionHasNoErrors();
            $this->assertNull(PricingSetting::where('product_type', $product)->firstOrFail()->markup_value);
            $this->put(route('admin.pricing.update', $product), ['adjustment_type' => 'markup', 'markup_value' => 10, 'enabled' => 0])->assertSessionHasNoErrors();
            $this->assertFalse(PricingSetting::where('product_type', $product)->firstOrFail()->enabled);
        }
    }

    public function test_fixed_amounts_negative_values_invalid_directions_and_excessive_markdowns_are_rejected(): void
    {
        $this->actingAs($this->admin());
        foreach (['airline', 'hotel'] as $product) {
            foreach ([['markup_type' => 'fixed', 'markup_value' => 10000], ['markup_value' => -1], ['adjustment_type' => 'fixed'], ['adjustment_type' => 'markdown', 'markup_value' => 100], ['adjustment_type' => 'markdown', 'markup_value' => 200]] as $bad) {
                $data = array_replace(['adjustment_type' => 'markup', 'markup_type' => 'percentage', 'markup_value' => 10, 'enabled' => 1], $bad);
                $field = $data['markup_type'] === 'fixed' ? 'markup_type' : ($data['adjustment_type'] === 'fixed' ? 'adjustment_type' : 'markup_value');
                $this->putJson(route('admin.pricing.update', $product), $data)->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_legacy_fixed_rule_shows_a_warning_and_never_prefills_its_amount_as_a_percentage(): void
    {
        $this->rule('airline', 'markup', 10000)->update(['markup_type' => 'fixed']);
        $this->actingAs($this->admin())->get(route('admin.pricing.edit', 'airline'))->assertOk()
            ->assertSee('previously saved fixed-amount rule is no longer applied')
            ->assertSee('data-pricing-percent', false)->assertDontSee('value="10000.0000"', false);
        $this->put(route('admin.pricing.update', 'airline'), ['adjustment_type' => 'markdown', 'markup_value' => 10, 'enabled' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pricing_settings', ['product_type' => 'airline', 'markup_type' => 'percentage', 'markup_value' => 10]);
    }

    public function test_unauthorized_users_cannot_change_default_pricing(): void
    {
        $this->actingAs(User::factory()->create(['account_type' => 'b2c']));
        $this->putJson(route('admin.pricing.update', 'hotel'), ['adjustment_type' => 'markdown', 'markup_value' => 10, 'enabled' => 1])->assertForbidden();
    }

    public function test_flight_search_review_and_revalidation_keep_the_markdown_once(): void
    {
        $this->rule('airline', 'markdown', 10);
        $response = $this->postJson('/api/v1/flights/search', $this->flightCriteria())->assertOk()
            ->assertJsonPath('data.offers.0.price.markdown_minor', 5250000)->assertJsonPath('data.offers.0.price.total_minor', 47250000);
        $offer = TravelOffer::findOrFail($response->json('data.offers.0.id'));
        $this->get(route('flights.review', $offer))->assertOk()->assertSee('Price discount')->assertSee('472,500.00');
        foreach ([1, 2] as $unused) {
            app(FlightRevalidationService::class)->revalidate($offer->fresh());
            $this->assertSame(47250000, (int) $offer->fresh()->selling_total_minor);
            $this->assertSame(5250000, (int) $offer->fresh()->markdown_minor);
        }
        $this->getJson('/api/v1/flights/offers/'.$offer->id.'?currency=NGN')->assertOk()->assertJsonPath('data.offer.price.discount_minor', 5250000);
    }

    public function test_flight_payment_initialization_uses_the_discounted_price(): void
    {
        $this->rule('airline', 'markdown', 10);
        config(['services.paystack.public_key' => 'pk_test_pricing', 'services.paystack.secret_key' => null]);
        $response = $this->postJson('/api/v1/flights/search', $this->flightCriteria())->assertOk();
        $offer = TravelOffer::findOrFail($response->json('data.offers.0.id'));
        $this->post(route('checkout.travellers.store', $offer), [
            'travellers' => [['type' => 'ADT', 'title' => 'Mr', 'first_name' => 'Test', 'last_name' => 'Guest', 'date_of_birth' => '1990-01-01', 'gender' => 'male', 'nationality' => 'NG', 'passport_number' => 'TEST12345', 'passport_country' => 'NG', 'passport_expiry' => today()->addYears(2)->toDateString()]],
            'contact' => ['email' => 'guest@example.com', 'phone' => '+2348000000000'],
        ])->assertSessionHasNoErrors();
        $this->postJson(route('checkout.payment.initialize', $offer), ['terms' => 1])->assertOk();
        $this->assertSame(47250000, (int) CheckoutPaymentAttempt::firstOrFail()->amount_minor);
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_hotel_search_rooms_and_payment_agree_on_discounted_prices(): void
    {
        $this->rule('hotel', 'markdown', 10);
        $criteria = ['destination_code' => 'LOS', 'destination_label' => 'Lagos, Nigeria', 'check_in' => today()->addWeeks(2)->toDateString(), 'check_out' => today()->addWeeks(2)->addDays(2)->toDateString(), 'adults' => 2, 'children' => 0, 'rooms' => 1, 'currency' => 'NGN', 'session_id' => (string) Str::uuid()];
        $response = $this->postJson('/api/v1/hotels/search', $criteria)->assertOk()
            ->assertJsonPath('data.offers.0.price.total_minor', 56700000)
            ->assertJsonPath('data.offers.0.price.nightly_minor', 28350000);
        $offer = HotelOffer::findOrFail($response->json('data.offers.0.id'));
        $this->get(route('hotels.rooms', $offer))->assertOk()->assertSee('567,000.00')->assertSee('283,500.00');
        config(['services.paystack.public_key' => 'pk_test_pricing', 'services.paystack.secret_key' => null]);
        $this->postJson(route('hotels.checkout.payment', $offer), ['first_name' => 'Test', 'last_name' => 'Guest', 'email' => 'guest@example.com', 'phone_code' => '+234', 'phone' => '8000000000', 'terms' => 1])->assertOk()->assertJsonPath('amount_minor', 56700000);
        $this->assertSame(56700000, (int) CheckoutPaymentAttempt::firstOrFail()->amount_minor);
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    private function rule(string $product, string $direction, float $percent): PricingSetting
    {
        $setting = PricingSetting::where('product_type', $product)->firstOrFail();
        $setting->update(['markup_type' => 'percentage', 'adjustment_type' => $direction, 'markup_value' => $percent, 'enabled' => true]);

        return $setting;
    }

    private function flight(int $total): FlightOffer
    {
        return new FlightOffer('test-offer', 'KQ', [['origin' => 'LOS', 'destination' => 'ABV', 'departure_at' => today()->addWeek()->toIso8601String()]], 'USD', $total - 1000, 1000, $total, false);
    }

    private function flightCriteria(): array
    {
        return ['origin' => 'LOS', 'destination' => 'ABV', 'departure_date' => today()->addWeek()->toDateString(), 'trip_type' => 'one_way', 'cabin' => 'economy', 'adults' => 1, 'currency' => 'NGN', 'session_id' => (string) Str::uuid()];
    }

    private function admin(): User
    {
        $user = User::factory()->create(['account_type' => 'admin']);
        $role = Role::create(['name' => 'test-pricing-admin', 'label' => 'Pricing admin']);
        $role->permissions()->attach(Permission::create(['name' => 'offers.manage', 'label' => 'Manage pricing']));
        $user->roles()->attach($role);

        return $user;
    }
}

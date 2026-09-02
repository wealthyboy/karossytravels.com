<?php

namespace Tests\Feature;

use App\Models\CurrencySetting;
use App\Models\Permission;
use App\Models\PricingSetting;
use App\Models\Role;
use App\Models\User;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PricingAndCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_airline_default_markup_is_applied_to_flight_offers(): void
    {
        PricingSetting::where('product_type', 'airline')->update(['markup_value' => 10, 'markup_type' => 'percentage']);

        $this->postJson('/api/v1/flights/search', [
            'origin' => 'LOS', 'destination' => 'ABV', 'departure_date' => now()->addWeek()->toDateString(),
            'trip_type' => 'one_way', 'cabin' => 'economy', 'adults' => 1, 'currency' => 'NGN', 'session_id' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('data.offers.0.price.markup_minor', 5250000)
            ->assertJsonPath('data.offers.0.price.total_minor', 57750000);
    }

    public function test_manual_rate_and_live_rate_markup_are_applied(): void
    {
        CurrencySetting::where('code', 'NGN')->update(['manual_rate' => 1500, 'adjustment_type' => 'markup', 'adjustment_percent' => 2]);
        $converted = app(ExchangeRateService::class)->convertMinor(10000, 'USD', 'NGN');

        $this->assertSame('NGN', $converted['currency']);
        $this->assertSame(15300000, $converted['amount_minor']);
        $this->assertSame(1530.0, $converted['rate']);
    }

    public function test_fixed_currency_markup_changes_the_customer_rate(): void
    {
        CurrencySetting::where('code', 'NGN')->update([
            'manual_rate' => 1500,
            'adjustment_type' => 'markup',
            'adjustment_mode' => 'fixed',
            'adjustment_percent' => 25,
        ]);

        $converted = app(ExchangeRateService::class)->convertMinor(10000, 'USD', 'NGN');

        $this->assertSame(1525.0, $converted['rate']);
        $this->assertSame(15250000, $converted['amount_minor']);
    }

    public function test_configured_fallback_rate_keeps_naira_conversion_available_when_live_rates_fail(): void
    {
        Cache::forget('travel:exchange-rates:USD');
        Http::fake([config('travel.currency.rates_url') => Http::response([], 503)]);
        CurrencySetting::where('code', 'NGN')->update(['manual_rate' => null]);
        config()->set('travel.currency.fallback_usd_rates.NGN', 1600);

        $converted = app(ExchangeRateService::class)->convertMinor(10000, 'USD', 'NGN');

        $this->assertSame('NGN', $converted['currency']);
        $this->assertSame(16000000, $converted['amount_minor']);
    }

    public function test_country_header_selects_naira_in_nigeria_and_usd_elsewhere(): void
    {
        $this->withHeader('CF-IPCountry', 'NG')->get('/')->assertOk()->assertSee('NGN');
        $this->withHeader('CF-IPCountry', 'GB')->get('/')->assertOk()->assertSee('USD');
    }

    public function test_frontend_search_uses_trusted_location_currency_and_applies_airline_markup(): void
    {
        PricingSetting::where('product_type', 'airline')->update([
            'markup_value' => 10,
            'markup_type' => 'percentage',
            'enabled' => true,
        ]);

        $criteria = [
            'origin' => 'LOS',
            'destination' => 'ABV',
            'departure_date' => now()->addWeek()->toDateString(),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            // A manipulated browser value must not override the trusted resolver.
            'currency' => 'USD',
            'session_id' => (string) Str::uuid(),
        ];

        $this->withHeader('CF-IPCountry', 'NG')
            ->get(route('flights.results', $criteria))
            ->assertOk()
            ->assertSee('Finding your best flights');

        $this->assertDatabaseCount('flight_searches', 0);

        $this->withHeader('CF-IPCountry', 'NG')
            ->postJson(route('flights.search.store'), $criteria)
            ->assertOk()
            ->assertJsonPath('meta.currency', 'NGN');

        $this->assertDatabaseHas('flight_searches', ['currency' => 'NGN']);
        $this->assertDatabaseHas('travel_offers', [
            'provider_total_minor' => 52500000,
            'markup_minor' => 5250000,
            'selling_total_minor' => 57750000,
        ]);
    }

    public function test_signed_in_account_currency_takes_priority_over_ip_currency(): void
    {
        $user = User::factory()->create(['currency_code' => 'USD']);

        $this->actingAs($user)
            ->withHeader('CF-IPCountry', 'NG')
            ->get('/')
            ->assertOk()
            ->assertSee('USD');
    }

    public function test_user_can_switch_and_persist_a_supported_display_currency(): void
    {
        $user = $this->authorizedAdmin();

        $this->actingAs($user)
            ->from('/admin/settings/currency')
            ->post(route('currency.update'), ['currency' => 'GBP'])
            ->assertRedirect('/admin/settings/currency')
            ->assertSessionHas('display_currency', 'GBP');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'currency_code' => 'GBP']);
        $this->get('/admin/settings/currency')->assertOk()
            ->assertSee('Switch display currency')
            ->assertSee('United Kingdom');
    }

    public function test_switching_currency_reprices_hotel_results_with_the_live_rate(): void
    {
        Cache::forget('travel:exchange-rates:USD');
        Http::fake([config('travel.currency.rates_url') => Http::response(['rates' => [
            'USD' => 1,
            'GBP' => 0.8,
        ]])]);

        $this->from('/')->post(route('currency.update'), ['currency' => 'GBP'])
            ->assertRedirect('/')
            ->assertSessionHas('display_currency', 'GBP');

        $criteria = [
            'destination_code' => 'LOS',
            'destination_label' => 'Lagos, Nigeria',
            'check_in' => now()->addDays(14)->toDateString(),
            'check_out' => now()->addDays(16)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'rooms' => 1,
            'session_id' => (string) Str::uuid(),
        ];

        $this->get(route('hotels.results', $criteria))->assertOk()
            ->assertSee('Searching live hotel availability');

        $response = $this->postJson(route('hotels.search.store'), $criteria)->assertOk();
        $this->assertStringContainsString('GBP selected for your location', $response->json('data.html'));
        $this->assertStringContainsString('£336.00', $response->json('data.html'));
    }

    public function test_authorized_admin_can_update_pricing_and_currency_settings(): void
    {
        $admin = $this->authorizedAdmin();
        $this->actingAs($admin)->put('/admin/pricing/airline', ['markup_type' => 'percentage', 'markup_value' => 7.5, 'currency' => 'USD', 'enabled' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pricing_settings', ['product_type' => 'airline', 'markup_value' => 7.5]);

        $ngn = CurrencySetting::where('code', 'NGN')->firstOrFail();
        $usd = CurrencySetting::where('code', 'USD')->firstOrFail();
        $this->actingAs($admin)->put('/admin/settings/currency', ['currencies' => [
            $usd->id => ['manual_rate' => 1, 'adjustment_type' => 'none', 'enabled' => 1],
            $ngn->id => ['manual_rate' => 1500, 'adjustment_type' => 'markdown', 'adjustment_percent' => 1, 'enabled' => 1],
        ]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('currency_settings', ['code' => 'NGN', 'adjustment_type' => 'markdown']);
    }

    public function test_authorized_admin_can_clear_and_refresh_exchange_rates(): void
    {
        Cache::put('travel:exchange-rates:USD', ['NGN' => 1000], now()->addHour());
        Http::fake([config('travel.currency.rates_url') => Http::response(['rates' => ['NGN' => 1600, 'GBP' => 0.75]])]);

        $this->actingAs($this->authorizedAdmin())
            ->post('/admin/settings/currency/refresh')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1600, Cache::get('travel:exchange-rates:USD')['NGN']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'currency.rates_refreshed']);
    }

    private function authorizedAdmin(): User
    {
        $user = User::factory()->create(['account_type' => 'admin']);
        $role = Role::create(['name' => 'pricing-admin', 'label' => 'Pricing Administrator']);
        $permissions = collect(['offers.manage', 'settings.manage'])->map(fn ($name) => Permission::create(['name' => $name, 'label' => str($name)->headline()]));
        $role->permissions()->attach($permissions);
        $user->roles()->attach($role);

        return $user;
    }
}

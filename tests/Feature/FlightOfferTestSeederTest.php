<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\CheckoutPaymentAttempt;
use App\Models\CurrencySetting;
use App\Models\FlightDeal;
use App\Models\FlightOffer;
use App\Models\PricingSetting;
use App\Models\TravelOffer;
use App\Travel\Contracts\FlightProvider;
use App\Travel\FlightRevalidationService;
use Database\Seeders\FlightOfferTestSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FlightOfferTestSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00', 'Africa/Lagos'));
        Storage::fake('local');
        Storage::fake('public');
        Http::preventStrayRequests();
    }

    public function test_reset_creates_exactly_four_future_cards_and_backs_up_old_cards_and_linked_rules(): void
    {
        $standalone = $this->oldDeal();
        $linked = $this->oldDeal();
        Storage::disk('public')->put('flight-offers/preserved.jpg', 'fixture');
        $old = $this->oldOffer($linked->id);
        $currency = CurrencySetting::where('code', 'NGN')->firstOrFail()->toArray();
        $pricing = PricingSetting::where('product_type', 'airline')->firstOrFail()->toArray();
        $this->seed(FlightOfferTestSeeder::class);

        $this->assertDatabaseCount('flight_offers', 4);
        $this->assertDatabaseMissing('flight_offers', ['id' => $old->id]);
        $this->assertFalse($linked->fresh()->active);
        $this->assertTrue($standalone->fresh()->active);
        $this->assertSame($currency, CurrencySetting::where('code', 'NGN')->firstOrFail()->toArray());
        $this->assertSame($pricing, PricingSetting::where('product_type', 'airline')->firstOrFail()->toArray());
        Storage::disk('public')->assertExists('flight-offers/preserved.jpg');
        $paths = Storage::disk('local')->files('flight-offer-test-backups');
        $this->assertCount(1, $paths);
        $backup = json_decode(Storage::disk('local')->get($paths[0]), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($old->id, $backup['flight_offers'][0]['id']);
        $this->assertSame($linked->id, $backup['linked_flight_deals'][0]['id']);
        $this->assertTrue($backup['linked_flight_deals'][0]['active']);

        $offers = FlightOffer::with('deal')->orderBy('sort_order')->get();
        $this->assertSame(['LHR', 'DXB', 'CDG', 'DOH'], $offers->pluck('destination_airport')->all());
        foreach ($offers as $offer) {
            $this->assertTrue($offer->active && $offer->promotion_enabled && $offer->deal->active);
            $this->assertTrue($offer->departure_date->isFuture());
            $this->assertTrue($offer->return_date->greaterThan($offer->departure_date));
            $this->assertTrue($offer->departure_date->betweenIncluded($offer->deal->travel_from, $offer->deal->travel_until));
            $this->assertSame('LOS', $offer->deal->origin_airport);
            $this->assertSame($offer->destination_airport, $offer->deal->destination_airport);
            $this->assertSame('USD', $offer->currency);
            $this->assertSame('economy', $offer->cabin);
            $this->assertGreaterThan($standalone->priority, $offer->deal->priority);
        }
        $this->assertSame(['QR', 'TK'], $offers[0]->deal->airline_codes);
        $this->assertTrue($offers[1]->deal->all_airlines);
        $this->assertSame(['AF'], $offers[2]->deal->airline_codes);
        $this->assertSame(['QR'], $offers[3]->deal->airline_codes);
    }

    public function test_rerunning_refreshes_dates_without_duplicating_cards_or_leaving_old_promotions_active(): void
    {
        $this->seed(FlightOfferTestSeeder::class);
        $oldIds = FlightOffer::pluck('flight_deal_id')->all();
        $this->travelTo(now()->addWeek());
        $this->seed(FlightOfferTestSeeder::class);
        $this->assertDatabaseCount('flight_offers', 4);
        $this->assertSame(0, FlightDeal::whereIn('id', $oldIds)->where('active', true)->count());
        $this->assertSame(4, FlightDeal::where('active', true)->count());
        $this->assertCount(2, Storage::disk('local')->files('flight-offer-test-backups'));
        $this->assertSame('2026-11-04', FlightOffer::where('destination_airport', 'LHR')->firstOrFail()->departure_date->toDateString());
    }

    public function test_missing_airline_aborts_before_replacing_any_records(): void
    {
        $old = $this->oldOffer($this->oldDeal()->id);
        Airline::where('code', 'QR')->update(['active' => false]);
        try {
            $this->seed(FlightOfferTestSeeder::class);
            $this->fail('Seeder should refuse missing active airlines.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Active QR, TK, EK and AF', $error->getMessage());
        }
        $this->assertDatabaseHas('flight_offers', ['id' => $old->id]);
        $this->assertTrue($old->deal->active);
        $this->assertCount(0, Storage::disk('local')->files('flight-offer-test-backups'));
    }

    public function test_production_requires_an_explicit_forced_command_before_any_data_changes(): void
    {
        $old = $this->oldOffer($this->oldDeal()->id);
        $this->app->instance('env', 'production');
        try {
            app(FlightOfferTestSeeder::class)->run();
            $this->fail('Seeder should refuse an unforced production invocation.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('--force', $error->getMessage());
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertDatabaseHas('flight_offers', ['id' => $old->id]);
        $this->assertTrue($old->deal->active);
    }

    public function test_production_forced_command_replaces_cards_only_after_a_verified_backup(): void
    {
        $old = $this->oldOffer($this->oldDeal()->id);
        $this->app->instance('env', 'production');
        try {
            $this->artisan('db:seed', ['--class' => FlightOfferTestSeeder::class, '--force' => true])->assertSuccessful();
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertDatabaseCount('flight_offers', 4);
        $this->assertFalse($old->deal->fresh()->active);
        $paths = Storage::disk('local')->files('flight-offer-test-backups');
        $this->assertCount(1, $paths);
        $backup = json_decode(Storage::disk('local')->get($paths[0]), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($old->id, $backup['flight_offers'][0]['id']);
    }

    public function test_maximum_priority_conflict_aborts_without_deleting_offers(): void
    {
        $old = $this->oldOffer($this->oldDeal()->id);
        $this->oldDeal()->update(['priority' => 65535]);
        try {
            $this->seed(FlightOfferTestSeeder::class);
            $this->fail('Seeder should refuse an unresolved priority conflict.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('maximum priority', $error->getMessage());
        }
        $this->assertDatabaseHas('flight_offers', ['id' => $old->id]);
        $this->assertTrue($old->deal->fresh()->active);
    }

    public function test_backup_write_failure_leaves_all_existing_data_unchanged(): void
    {
        $old = $this->oldOffer($this->oldDeal()->id);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        try {
            $this->seed(FlightOfferTestSeeder::class);
            $this->fail('Seeder should refuse an unverifiable backup.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('No offers were replaced', $error->getMessage());
        }
        $this->assertDatabaseCount('flight_offers', 1);
        $this->assertDatabaseHas('flight_offers', ['id' => $old->id]);
        $this->assertTrue($old->deal->active);
    }

    public function test_existing_searches_and_live_offer_deal_references_survive_reset(): void
    {
        $old = $this->oldOffer($this->oldDeal()->id);
        $result = $this->postJson('/api/v1/flights/search', ['origin' => 'LOS', 'destination' => 'LHR', 'departure_date' => '2026-10-28', 'trip_type' => 'one_way', 'cabin' => 'economy', 'adults' => 1, 'currency' => 'NGN', 'session_id' => (string) Str::uuid()])->assertOk();
        $live = TravelOffer::findOrFail($result->json('data.offers.0.id'));
        $live->update(['flight_deal_id' => $old->flight_deal_id, 'deal_discount_minor' => 1000]);
        $before = $live->fresh()->toArray();
        $this->seed(FlightOfferTestSeeder::class);
        $this->assertSame($before, $live->fresh()->toArray());
        $this->assertDatabaseCount('flight_searches', 1);
        $this->assertDatabaseHas('flight_deals', ['id' => $old->flight_deal_id, 'active' => false]);
    }

    public function test_all_four_card_links_preserve_criteria_and_matching_discounts_through_payment_initialization(): void
    {
        $this->seed(FlightOfferTestSeeder::class);
        PricingSetting::where('product_type', 'airline')->update(['enabled' => false]);
        $this->withHeader('CF-IPCountry', 'GB');
        config(['services.paystack.public_key' => 'pk_test_seeder', 'services.paystack.secret_key' => null]);

        // Simulate supplier offers, without changing the application's real provider.
        $this->app->instance(FlightProvider::class, new class implements FlightProvider
        {
            public function name(): string
            {
                return 'fake';
            }

            public function search(array $criteria): array
            {
                $code = ['LHR' => 'QR', 'DXB' => 'EK', 'CDG' => 'AF', 'DOH' => 'QR'][$criteria['destination']];

                return [[
                    'id' => (string) Str::uuid(), 'validating_airline' => $code,
                    'segments' => [
                        ['origin' => $criteria['origin'], 'destination' => $criteria['destination'], 'departure_at' => $criteria['departure_date'].'T09:00:00+01:00', 'arrival_at' => $criteria['departure_date'].'T15:00:00+01:00', 'marketing_airline' => $code, 'flight_number' => $code.'101', 'cabin' => $criteria['cabin']],
                        ['origin' => $criteria['destination'], 'destination' => $criteria['origin'], 'departure_at' => $criteria['return_date'].'T09:00:00+01:00', 'arrival_at' => $criteria['return_date'].'T15:00:00+01:00', 'marketing_airline' => $code, 'flight_number' => $code.'102', 'cabin' => $criteria['cabin']],
                    ],
                    // Deliberately differs from the card estimate: live pricing wins.
                    'price' => ['currency' => 'USD', 'base_minor' => 80000, 'taxes_minor' => 20000, 'total_minor' => 100000],
                    'refundable' => false,
                ]];
            }
        });
        $homepage = $this->get('/')->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML($homepage->getContent());
        $cards = (new DOMXPath($document))->query('//a[contains(@class, "homepage-flight-offer")]');
        $this->assertSame(4, $cards->length);

        foreach ($cards as $index => $card) {
            // Separate test visitors, so each checkout has its own rate-limit bucket.
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($index + 1)]);
            $url = $card->getAttribute('href');
            parse_str(parse_url($url, PHP_URL_QUERY), $criteria);
            $seed = FlightOffer::with('deal')->where('destination_airport', $criteria['destination'])->firstOrFail();
            $this->assertSame($seed->departure_date->toDateString(), $criteria['departure_date']);
            $this->assertSame($seed->return_date->toDateString(), $criteria['return_date']);
            $this->assertSame($seed->origin_airport, $criteria['origin']);
            $this->assertSame($seed->cabin, $criteria['cabin']);
            $this->assertSame('round_trip', $criteria['trip_type']);
            $this->get($url)->assertOk()->assertSee('Finding your best flights');
            $result = $this->postJson(route('flights.search.store'), $criteria)->assertOk();
            $live = TravelOffer::findOrFail($result->json('data.offers.0.id'));
            $discount = (int) round(100000 * (float) $seed->deal->discount_value / 100);
            $total = 100000 - $discount;
            $this->assertSame($seed->flight_deal_id, $live->flight_deal_id);
            $this->assertSame($discount, (int) $live->deal_discount_minor);
            $this->assertSame($total, (int) $live->selling_total_minor);
            $this->assertSame($seed->departure_date->toDateString(), $live->flightSearch->departure_date->toDateString());
            $this->assertSame($seed->return_date->toDateString(), $live->flightSearch->return_date->toDateString());
            $this->assertSame($seed->cabin, $live->flightSearch->cabin);
            $this->assertSame($seed->airline_code, data_get($live->fare_summary, 'validating_airline'));
            foreach ([1, 2] as $unused) {
                app(FlightRevalidationService::class)->revalidate($live->fresh());
                $this->assertSame($total, (int) $live->fresh()->selling_total_minor);
            }
            $this->get(route('checkout.travellers', $live))->assertOk();
            $this->post(route('checkout.travellers.store', $live), [
                'travellers' => [['type' => 'ADT', 'title' => 'Mr', 'first_name' => 'Test', 'last_name' => 'Guest', 'date_of_birth' => '1990-01-01', 'gender' => 'male', 'nationality' => 'NG', 'passport_number' => 'TEST12345', 'passport_country' => 'NG', 'passport_expiry' => '2030-01-01']],
                'contact' => ['email' => "test-seed-{$index}@example.com", 'phone' => '+2348000000000'],
            ])->assertSessionHasNoErrors();
            $this->postJson(route('checkout.payment.initialize', $live), ['terms' => 1])->assertOk();
            $attempt = CheckoutPaymentAttempt::where('travel_offer_id', $live->id)->firstOrFail();
            $this->assertSame($total, (int) $attempt->amount_minor);
            $this->assertSame('USD', $attempt->currency);
        }
        $this->assertDatabaseCount('checkout_payment_attempts', 4);
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    private function oldDeal(): FlightDeal
    {
        return FlightDeal::create(['airline_id' => Airline::where('code', 'QR')->firstOrFail()->id, 'airline_codes' => ['QR'], 'origin_airport' => 'LOS', 'destination_airport' => 'LHR', 'travel_from' => today(), 'travel_until' => today()->addMonths(2), 'discount_type' => 'percentage', 'discount_value' => 1, 'active' => true]);
    }

    private function oldOffer(int $dealId): FlightOffer
    {
        return FlightOffer::create(['slug' => 'old-card', 'origin_airport' => 'LOS', 'origin_city' => 'Lagos', 'destination_airport' => 'LHR', 'destination_city' => 'London', 'airline_name' => 'Qatar Airways', 'airline_code' => 'QR', 'departure_date' => today()->addMonth(), 'return_date' => today()->addMonth()->addWeek(), 'cabin' => 'economy', 'price_minor' => 100000, 'currency' => 'USD', 'image_path' => 'flight-offers/preserved.jpg', 'active' => true, 'promotion_enabled' => true, 'flight_deal_id' => $dealId]);
    }
}

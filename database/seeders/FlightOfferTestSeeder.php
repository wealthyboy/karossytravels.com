<?php

namespace Database\Seeders;

use App\Models\Airline;
use App\Models\FlightDeal;
use App\Models\FlightOffer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Explicit reset with a production --force gate. Never called from DatabaseSeeder. */
final class FlightOfferTestSeeder extends Seeder
{
    public function run(): void
    {
        $forced = $this->command?->getDefinition()->hasOption('force') && $this->command->option('force');
        if (app()->environment('production') && ! $forced) {
            throw new RuntimeException('FlightOfferTestSeeder replaces homepage offers. On production, run php artisan db:seed --class=FlightOfferTestSeeder --force explicitly.');
        }
        if (! Schema::hasColumn('flight_offers', 'promotion_enabled') || ! Schema::hasColumn('flight_deals', 'airline_codes')) {
            throw new RuntimeException('Apply the flight-promotion migration before running FlightOfferTestSeeder.');
        }
        if (! Schema::hasColumn('flight_searches', 'children_ages')) {
            throw new RuntimeException('Apply the existing flight-search children_ages migration before seeding checkout test offers.');
        }

        $today = CarbonImmutable::now('Africa/Lagos')->startOfDay();
        $examples = $this->examples();
        $airlines = Airline::whereIn('code', ['QR', 'TK', 'EK', 'AF'])->where('active', true)->get()->keyBy('code');
        if ($airlines->count() !== 4) {
            throw new RuntimeException('Active QR, TK, EK and AF airlines are required. No offers were replaced.');
        }

        [$removed, $backup] = DB::transaction(function () use ($today, $examples, $airlines): array {
            $oldOffers = FlightOffer::query()->lockForUpdate()->get();
            $linkedIds = $oldOffers->pluck('flight_deal_id')->filter()->unique()->values();
            $oldDeals = FlightDeal::whereIn('id', $linkedIds)->lockForUpdate()->get();
            // Preserve unrelated rules, but let these test rules win when they overlap.
            $highestPriority = (int) FlightDeal::where('active', true)->whereNotIn('id', $linkedIds)->max('priority');
            if ($highestPriority >= 65535) {
                throw new RuntimeException('An unrelated active rule already uses maximum priority. Resolve that overlap before resetting test offers.');
            }
            $priority = $highestPriority + 1;

            $backup = 'flight-offer-test-backups/'.$today->format('Y-m-d').'_'.Str::ulid().'.json';
            $snapshot = json_encode([
                'created_at' => now('Africa/Lagos')->toIso8601String(),
                'purpose' => 'Before FlightOfferTestSeeder replaced homepage offers',
                'flight_offers' => $oldOffers->toArray(),
                'linked_flight_deals' => $oldDeals->toArray(),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (! Storage::disk('local')->put($backup, $snapshot)
                || Storage::disk('local')->get($backup) !== $snapshot) {
                throw new RuntimeException('Could not verify the offer backup. No offers were replaced.');
            }

            // Keep historical deal IDs intact for existing searches/bookings.
            FlightDeal::whereIn('id', $linkedIds)->update(['active' => false]);
            $removed = FlightOffer::whereKey($oldOffers->modelKeys())->delete();

            foreach ($examples as $index => $example) {
                $departure = $today->addDays(14 + $index * 7);
                $all = $example['airlines'] === [];
                $featured = $airlines[$example['featured']];
                $deal = FlightDeal::create([
                    'airline_id' => $all ? null : $airlines[$example['airlines'][0]]->id,
                    'airline_codes' => $example['airlines'], 'all_airlines' => $all,
                    'origin_airport' => 'LOS', 'destination_airport' => $example['airport'],
                    'booking_from' => $today->toDateString(), 'booking_until' => $today->addDays(30)->toDateString(),
                    'travel_from' => $today->addDays(7)->toDateString(), 'travel_until' => $today->addDays(60)->toDateString(),
                    'discount_type' => 'percentage', 'discount_value' => $example['discount'], 'discount_currency' => null,
                    'priority' => $priority, 'active' => true,
                ]);
                FlightOffer::create([
                    'slug' => 'test-los-'.strtolower($example['airport']),
                    'origin_airport' => 'LOS', 'origin_city' => 'Lagos',
                    'destination_airport' => $example['airport'], 'destination_city' => $example['city'],
                    'airline_name' => $featured->name, 'airline_code' => $featured->code,
                    'departure_date' => $departure->toDateString(), 'return_date' => $departure->addDays(7)->toDateString(),
                    'cabin' => 'economy', 'price_minor' => $example['price_minor'], 'currency' => 'USD',
                    'image_url' => $example['image'], 'label' => 'TEST · '.$example['discount'].'% OFF',
                    'sort_order' => ($index + 1) * 10, 'active' => true,
                    'promotion_enabled' => true, 'flight_deal_id' => $deal->id,
                ]);
            }

            return [$removed, $backup];
        });

        $this->command?->info("Replaced {$removed} homepage flight offers with 4 linked test promotions.");
        $this->command?->line('Backup: '.Storage::disk('local')->path($backup));
        $this->command?->warn('Card prices are estimates. Matching live fares receive the promotion; no supplier, payment, booking or upload data was reset.');
        if (app()->environment('production')) {
            $this->command?->warn('These TEST cards and discounts are visible to production visitors. Real payment and ticketing remain enabled if configured; stop before payment unless a real booking is intended.');
        }
    }

    private function examples(): array
    {
        return [
            ['airport' => 'LHR', 'city' => 'London', 'featured' => 'QR', 'airlines' => ['QR', 'TK'], 'discount' => 10, 'price_minor' => 148500, 'image' => 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?auto=format&fit=crop&w=1000&q=82'],
            ['airport' => 'DXB', 'city' => 'Dubai', 'featured' => 'EK', 'airlines' => [], 'discount' => 12, 'price_minor' => 90000, 'image' => 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?auto=format&fit=crop&w=1000&q=82'],
            ['airport' => 'CDG', 'city' => 'Paris', 'featured' => 'AF', 'airlines' => ['AF'], 'discount' => 15, 'price_minor' => 110000, 'image' => 'https://images.unsplash.com/photo-1502602898657-3e91760cbb34?auto=format&fit=crop&w=1000&q=82'],
            // Doha photo: https://unsplash.com/photos/KUpxfPfQ_-E (tarek suman).
            ['airport' => 'DOH', 'city' => 'Doha', 'featured' => 'QR', 'airlines' => ['QR'], 'discount' => 5, 'price_minor' => 85000, 'image' => 'https://images.unsplash.com/photo-1596986343464-332d54fa5702?auto=format&fit=crop&w=1000&q=82'],
        ];
    }
}

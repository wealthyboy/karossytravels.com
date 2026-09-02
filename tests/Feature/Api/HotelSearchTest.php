<?php

namespace Tests\Feature\Api;

use App\Models\PricingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HotelSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_priced_hotel_offers_and_logs_the_search(): void
    {
        PricingSetting::query()->where('product_type', 'hotel')->update([
            'markup_type' => 'percentage', 'markup_value' => 10, 'enabled' => true,
        ]);
        $response = $this->postJson('/api/v1/hotels/search', [
            'destination_code' => 'LOS', 'destination_label' => 'Lagos, Nigeria',
            'check_in' => now()->addDays(14)->toDateString(), 'check_out' => now()->addDays(16)->toDateString(),
            'adults' => 2, 'children' => 0, 'rooms' => 1, 'currency' => 'USD', 'session_id' => (string) Str::uuid(),
        ]);

        $response->assertOk()->assertJsonPath('meta.offer_count', 1)->assertJsonPath('data.offers.0.name', 'Karossy Grand Hotel');
        $this->assertDatabaseCount('hotel_searches', 1);
        $this->assertDatabaseCount('hotel_offers', 1);
        $this->assertDatabaseHas('hotel_offers', ['provider_total_minor' => 42000, 'markup_minor' => 4200, 'selling_total_minor' => 46200]);
        $this->assertDatabaseHas('analytics_events', ['event' => 'hotel_search_completed']);
    }
}

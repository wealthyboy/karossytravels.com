<?php

namespace Tests\Feature;

use App\Models\HotelSearch;
use App\Support\HotelSearchRecovery;
use App\Travel\Contracts\HotelProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class HotelChildAgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_ages_are_saved_unchanged_and_preserved_when_recovering_a_search(): void
    {
        Http::preventStrayRequests();
        $this->withHeader('CF-IPCountry', 'GB')->postJson('/api/v1/hotels/search', [...$this->criteria(), 'children' => 2, 'children_ages' => [0, 17]])->assertOk();
        $search = HotelSearch::firstOrFail();
        $this->assertSame([0, 17], $search->children_ages);
        $this->assertSame(2, $search->children);
        $this->assertSame([0, 17], HotelSearchRecovery::parameters($search)['children_ages']);
        $this->get(route('hotels.results', HotelSearchRecovery::parameters($search)))->assertOk()->assertSee('aria-label="Child 1 age"', false)->assertSee('aria-label="Child 2 age"', false)->assertSee('Under 1');
    }

    #[DataProvider('invalidAges')]
    public function test_invalid_or_missing_ages_are_rejected_before_the_supplier_is_called(array $ages): void
    {
        $this->mock(HotelProvider::class)->shouldNotReceive('search');
        $this->postJson('/api/v1/hotels/search', [...$this->criteria(), 'children' => 2, 'children_ages' => $ages])->assertUnprocessable();
        $this->postJson(route('hotels.search.store'), [...$this->criteria(), 'children' => 2, 'children_ages' => $ages])->assertUnprocessable();
        $this->assertDatabaseCount('hotel_searches', 0);
    }

    public static function invalidAges(): array
    {
        return ['missing' => [[]], 'too few' => [[7]], 'too many' => [[0, 7, 17]], 'blank' => [[null, 7]], 'negative' => [[-1, 7]], 'over 17' => [[18, 7]], 'fraction' => [[2.5, 7]], 'boolean' => [[true, 7]], 'non numeric' => [['Under 1', 7]], 'not a list' => [['first' => 0, 'second' => 7]]];
    }

    public function test_multiple_rooms_require_the_equal_guest_counts_documented_by_sabre(): void
    {
        $this->mock(HotelProvider::class)->shouldNotReceive('search');
        $this->postJson('/api/v1/hotels/search', [...$this->criteria(), 'adults' => 3, 'rooms' => 2])->assertUnprocessable()->assertJsonValidationErrors('rooms');
        $this->postJson('/api/v1/hotels/search', [...$this->criteria(), 'adults' => 4, 'rooms' => 2, 'children' => 1, 'children_ages' => [0]])->assertUnprocessable()->assertJsonValidationErrors('rooms');
    }

    public function test_extra_ages_with_zero_children_are_rejected(): void
    {
        $this->postJson('/api/v1/hotels/search', [...$this->criteria(), 'children_ages' => [0]])->assertUnprocessable()->assertJsonValidationErrors('children_ages');
    }

    private function criteria(): array
    {
        return ['destination_code' => 'LOS', 'destination_label' => 'Lagos, Nigeria', 'check_in' => today()->addWeek()->toDateString(), 'check_out' => today()->addWeek()->addDays(3)->toDateString(), 'adults' => 2, 'children' => 0, 'rooms' => 1, 'currency' => 'USD', 'session_id' => '0f9405eb-1674-44d2-b6f8-a2a607c26f50'];
    }
}

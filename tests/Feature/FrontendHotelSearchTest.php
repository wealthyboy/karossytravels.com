<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FrontendHotelSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_search_a_complete_hotel_stay_using_check_in_and_check_out_dates(): void
    {
        $checkIn = now()->addDays(14)->startOfDay();
        $checkOut = now()->addDays(18)->startOfDay();

        $response = $this->get(route('hotels.results', [
            'destination_code' => 'LOS',
            'destination_label' => 'Lagos, Nigeria',
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'adults' => 2,
            'children' => 0,
            'rooms' => 1,
            'currency' => 'NGN',
            'session_id' => (string) Str::uuid(),
        ]));

        $response->assertOk()
            ->assertSee('Hotels in Lagos, Nigeria')
            ->assertSee($checkIn->format('M j').' – '.$checkOut->format('M j'))
            ->assertSee('Karossy Grand Hotel')
            ->assertSee('2 travellers · 1 room')
            ->assertSee('id="inlineHotelSearch"', false)
            ->assertSee('value="Lagos, Nigeria"', false)
            ->assertSee('value="LOS"', false);

        $this->assertDatabaseHas('hotel_searches', [
            'destination_code' => 'LOS',
            'check_in' => $checkIn->toDateTimeString(),
            'check_out' => $checkOut->toDateTimeString(),
            'channel' => 'consumer',
        ]);
        $this->assertDatabaseHas('travel_logs', [
            'product_type' => 'hotel',
            'stage' => 'search',
            'status' => 'success',
        ]);
    }
}

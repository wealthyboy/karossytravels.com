<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\FlightOffer;
use App\Models\Order;
use App\Models\CurrencySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_navigation_contains_study_program_and_manage_booking(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Student Study Program')
            ->assertSee(route('study-program'))
            ->assertSee('Manage booking')
            ->assertSee(route('manage-booking.index'));

        $this->get(route('study-program'))
            ->assertOk()
            ->assertSee('Your education journey, carefully planned.')
            ->assertSee('Start an enquiry');

        $this->get(route('manage-booking.index'))
            ->assertOk()
            ->assertSee('Find your booking')
            ->assertSee('data-manage-booking-form', false);
    }

    public function test_public_visa_search_uses_searchable_country_selects_and_whatsapp_link(): void
    {
        config()->set('travel.support.whatsapp', '+234 816 938 9886');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-searchable-select', false)
            ->assertSee('data-search-placeholder="Search passport country"', false)
            ->assertSee('data-search-placeholder="Search destination"', false)
            ->assertSee('class="public-whatsapp-float"', false)
            ->assertSee('https://wa.me/2348169389886', false);

        $this->get(route('visas.index'))
            ->assertOk()
            ->assertSee('data-searchable-select', false);
    }

    public function test_homepage_destination_mood_tabs_have_switchable_panels(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-destination-tabs', false)
            ->assertSee('data-destination-tab="beach"', false)
            ->assertSee('data-destination-tab="culture"', false)
            ->assertSee('data-destination-tab="family"', false)
            ->assertSee('data-destination-tab="wellness"', false)
            ->assertSee('data-destination-panel="beach"', false)
            ->assertSee('data-destination-panel="culture"', false)
            ->assertSee('data-destination-panel="family"', false)
            ->assertSee('data-destination-panel="wellness"', false)
            ->assertSee('Santorini')
            ->assertSee('Istanbul')
            ->assertSee('London')
            ->assertSee('Bali');
    }

    public function test_homepage_places_dynamic_flight_offers_after_explore_more_and_links_to_live_search(): void
    {
        $offer = FlightOffer::create([
            'slug' => 'los-lhr-test-offer',
            'origin_airport' => 'LOS',
            'origin_city' => 'Lagos',
            'destination_airport' => 'LHR',
            'destination_city' => 'London',
            'airline_name' => 'Virgin Atlantic',
            'airline_code' => 'VS',
            'departure_date' => today()->addMonth(),
            'return_date' => today()->addMonth()->addDays(5),
            'cabin' => 'economy',
            'price_minor' => 148500,
            'currency' => 'USD',
            'label' => 'Direct route',
            'sort_order' => 10,
            'active' => true,
        ]);

        $this->withHeader('CF-IPCountry', 'GB')->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder([
                'Explore more',
                'Flight offers',
                'Private charter flights',
            ])
            ->assertSee('Lagos')
            ->assertSee('London')
            ->assertSee('Virgin Atlantic')
            ->assertSee('$1,485')
            ->assertSee('origin=LOS', false)
            ->assertSee('destination=LHR', false)
            ->assertSee('Request a charter')
            ->assertSee('subject=Charter%20flight%20request', false);

        $this->get(route('flights.results', [
            'origin' => $offer->origin_airport,
            'destination' => $offer->destination_airport,
            'departure_date' => $offer->departure_date->toDateString(),
            'return_date' => $offer->return_date->toDateString(),
            'trip_type' => 'round_trip',
            'cabin' => $offer->cabin,
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'session_id' => '0f9405eb-1674-44d2-b6f8-a2a607c26f50',
        ]))->assertOk()
            ->assertSee('LOS')
            ->assertSee('LHR');
    }

    public function test_homepage_limits_flight_offers_and_converts_them_to_the_selected_currency(): void
    {
        CurrencySetting::where('code', 'NGN')->update(['manual_rate' => 1500]);

        foreach (range(1, 5) as $index) {
            FlightOffer::create([
                'slug' => "homepage-offer-{$index}",
                'origin_airport' => 'LOS',
                'origin_city' => 'Lagos',
                'destination_airport' => 'LHR',
                'destination_city' => "London {$index}",
                'airline_name' => 'Karossy Air',
                'airline_code' => 'KA',
                'departure_date' => today()->addMonth(),
                'return_date' => today()->addMonth()->addDays(5),
                'cabin' => 'economy',
                'price_minor' => 10000,
                'currency' => 'USD',
                'sort_order' => $index,
                'active' => true,
            ]);
        }

        $response = $this->withHeader('CF-IPCountry', 'NG')->get(route('home'))
            ->assertOk()
            ->assertSee('₦150,000')
            ->assertDontSee('$100');

        $this->assertSame(4, substr_count($response->getContent(), 'class="homepage-flight-offer"'));

        $this->from(route('home'))->post(route('currency.update'), ['currency' => 'USD'])->assertRedirect(route('home'));
        $this->get(route('home'))->assertOk()->assertSee('$100')->assertDontSee('₦150,000');
    }

    public function test_homepage_promotes_the_student_study_program_after_the_mobile_app(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder([
                'Plan, book and manage your trip anywhere.',
                'Student Study Program',
                'More than a booking website.',
            ])
            ->assertSee('Explore the study program')
            ->assertSee(route('study-program'));
    }

    public function test_guest_can_securely_find_booking_with_reference_and_email(): void
    {
        [$order, $booking] = $this->createGuestBooking();

        $response = $this->post(route('manage-booking.lookup'), [
            'reference' => strtolower($order->reference),
            'email' => 'guest@example.com',
        ])->assertRedirect();

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee($order->reference)
            ->assertSee($booking->provider_locator)
            ->assertSee('Manage another booking');
    }

    public function test_guest_booking_lookup_rejects_an_email_that_does_not_match(): void
    {
        [$order] = $this->createGuestBooking();

        $this->from(route('manage-booking.index'))->post(route('manage-booking.lookup'), [
            'reference' => $order->reference,
            'email' => 'someone-else@example.com',
        ])->assertRedirect(route('manage-booking.index'))
            ->assertSessionHasErrors('reference');
    }

    private function createGuestBooking(): array
    {
        $order = Order::create([
            'reference' => 'KAR-REVIEW123',
            'channel' => 'frontend',
            'status' => 'confirmed',
            'currency' => 'USD',
            'subtotal_minor' => 120000,
            'total_minor' => 120000,
            'customer' => ['name' => 'Guest Traveller', 'email' => 'guest@example.com'],
        ]);

        $booking = Booking::create([
            'order_id' => $order->id,
            'product_type' => 'flight',
            'provider' => 'travel_api',
            'provider_locator' => 'ABC123',
            'status' => 'confirmed',
            'travellers' => [],
            'details' => ['itinerary' => []],
            'booked_at' => now(),
        ]);

        return [$order, $booking];
    }
}

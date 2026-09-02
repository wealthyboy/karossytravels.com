<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminFlightSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_admin_search_uses_the_admin_channel(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $role = Role::create(['name' => 'flight-operator', 'label' => 'Flight Operator']);
        $permission = Permission::create(['name' => 'bookings.view', 'label' => 'View Bookings']);
        $role->permissions()->attach($permission);
        $admin->roles()->attach($role);

        $this->actingAs($admin)->postJson('/admin/flights/search', [
            'origin' => 'LOS',
            'destination' => 'ABV',
            'departure_date' => now()->addWeek()->toDateString(),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'NGN',
            'session_id' => (string) Str::uuid(),
        ])->assertOk()->assertJsonCount(1, 'data.offers');

        $this->assertDatabaseHas('flight_searches', [
            'user_id' => $admin->id,
            'channel' => 'admin',
        ]);
        $this->assertDatabaseHas('travel_offers', ['channel' => 'admin']);
    }

    public function test_guest_cannot_discover_the_admin_search_endpoint(): void
    {
        $this->postJson('/admin/flights/search')->assertNotFound();
    }

    public function test_admin_can_revalidate_a_selected_round_trip_fare(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $role = Role::create(['name' => 'booking-agent', 'label' => 'Booking Agent']);
        $permissions = collect(['bookings.view', 'bookings.manage'])->map(
            fn (string $name) => Permission::create(['name' => $name, 'label' => str($name)->headline()])
        );
        $role->permissions()->attach($permissions);
        $admin->roles()->attach($role);

        $search = $this->actingAs($admin)->postJson('/admin/flights/search', [
            'origin' => 'LOS',
            'destination' => 'LHR',
            'departure_date' => now()->addWeek()->toDateString(),
            'return_date' => now()->addWeeks(2)->toDateString(),
            'trip_type' => 'round_trip',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'NGN',
            'session_id' => (string) Str::uuid(),
        ])->assertOk();

        $offerId = $search->json('data.offers.0.id');

        $this->postJson("/admin/flights/offers/{$offerId}/revalidate")
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.offer_id', $offerId)
            ->assertJsonPath('data.price_changed', false);

        $this->assertDatabaseMissing('travel_offers', ['id' => $offerId, 'last_validated_at' => null]);
        $this->assertDatabaseHas('analytics_events', ['event' => 'flight_offer_revalidated']);

        $customer = Customer::create([
            'first_name' => 'Ada', 'last_name' => 'Okafor', 'email' => 'ada@example.test',
            'phone' => '+2348012345678', 'status' => 'active',
        ]);
        $this->get("/admin/flights/offers/{$offerId}/book")->assertOk()->assertSee('No payment is collected');
        $this->post("/admin/flights/offers/{$offerId}/book", [
            'customer_id' => $customer->id,
            'travellers' => [[
                'type' => 'ADT', 'title' => 'Ms', 'first_name' => 'Ada', 'last_name' => 'Okafor',
                'date_of_birth' => '1990-05-12', 'gender' => 'female', 'nationality' => 'NG',
                'passport_number' => 'A12345678', 'passport_country' => 'NG',
                'passport_expiry' => now()->addYears(3)->toDateString(),
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('orders', ['customer_id' => $customer->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('bookings', ['travel_offer_id' => $offerId, 'status' => 'confirmed']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_admin_cannot_send_an_invalid_passenger_name_to_the_airline(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $role = Role::create(['name' => 'booking-agent', 'label' => 'Booking Agent']);
        $permissions = collect(['bookings.view', 'bookings.manage'])->map(
            fn (string $name) => Permission::create(['name' => $name, 'label' => str($name)->headline()])
        );
        $role->permissions()->attach($permissions);
        $admin->roles()->attach($role);

        $search = $this->actingAs($admin)->postJson('/admin/flights/search', [
            'origin' => 'LOS', 'destination' => 'LHR',
            'departure_date' => now()->addWeek()->toDateString(),
            'trip_type' => 'one_way', 'cabin' => 'economy',
            'adults' => 1, 'children' => 0, 'infants' => 0,
            'currency' => 'NGN', 'session_id' => (string) Str::uuid(),
        ])->assertOk();

        $offerId = $search->json('data.offers.0.id');
        $this->postJson("/admin/flights/offers/{$offerId}/revalidate")->assertOk();
        $customer = Customer::create([
            'first_name' => 'Jacob', 'last_name' => 'Atam', 'email' => 'jacob@example.test',
            'phone' => '+2348012345678', 'status' => 'active',
        ]);

        $this->from("/admin/flights/offers/{$offerId}/book")
            ->post("/admin/flights/offers/{$offerId}/book", [
                'customer_id' => $customer->id,
                'travellers' => [[
                    'type' => 'ADT', 'title' => 'Mr', 'first_name' => 'Jacob', 'last_name' => 'Atam2',
                    'date_of_birth' => '1990-01-01', 'gender' => 'male', 'nationality' => 'NG',
                    'passport_number' => 'A12345678', 'passport_country' => 'NG',
                    'passport_expiry' => now()->addYears(2)->toDateString(),
                ]],
            ])
            ->assertRedirect("/admin/flights/offers/{$offerId}/book")
            ->assertSessionHasErrors('travellers.0.last_name');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('bookings', 0);
    }
}

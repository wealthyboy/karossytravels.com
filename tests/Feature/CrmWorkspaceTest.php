<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class CrmWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_redirects_guests_to_sign_in(): void
    {
        $this->get('/crm')->assertRedirect(route('login'));
    }

    public function test_authorized_team_member_can_open_the_crm_dashboard_and_workspace(): void
    {
        $user = User::factory()->create(['account_type' => 'admin']);
        $role = Role::create(['name' => 'crm-operator', 'label' => 'CRM Operator']);
        $permissions = collect([
            ['name' => 'dashboard.view', 'label' => 'View dashboard'],
            ['name' => 'customers.view', 'label' => 'View customers'],
            ['name' => 'customers.manage', 'label' => 'Manage customers'],
        ])->map(fn (array $permission) => Permission::create($permission));
        $role->permissions()->attach($permissions->pluck('id'));
        $user->roles()->attach($role);

        $this->actingAs($user)->get('/crm')->assertOk()->assertSee('Travel CRM');
        $this->actingAs($user)->get('/crm/sales')->assertOk()->assertSee('Sales is being moved into this workspace');
        $this->actingAs($user)->get('/crm/calls')->assertOk()->assertSee('Dial a customer');
        $this->actingAs($user)->get(route('crm.customers.create'))->assertOk()->assertSee('Add customer');
        $this->actingAs($user)->post(route('crm.customers.store'), [
            'first_name' => 'Tomi', 'last_name' => 'Adebayo', 'email' => 'tomi@example.test', 'status' => 'active',
        ])->assertRedirect();
        $this->assertDatabaseHas('customers', ['email' => 'tomi@example.test']);

        $customer = Customer::create([
            'first_name' => 'Amina',
            'last_name' => 'Okafor',
            'email' => 'amina@example.test',
            'status' => 'active',
        ]);

        $this->actingAs($user)->get(route('crm.customers.show', $customer))
            ->assertOk()
            ->assertSee('Customer 360')
            ->assertSee('Amina Okafor');

        $this->actingAs($user)->post(route('crm.customers.activities.store', $customer), [
            'type' => 'call',
            'subject' => 'Welcome call',
            'body' => 'Customer asked for a flight quote.',
        ])->assertRedirect();

        $this->assertDatabaseHas('crm_activities', ['customer_id' => $customer->id, 'subject' => 'Welcome call']);
    }

    public function test_new_booking_is_automatically_owned_by_an_eligible_customer_agent(): void
    {
        $agent = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $role = Role::create(['name' => 'customer-agent', 'label' => 'Customer Agent']);
        $agent->roles()->attach($role);
        $customer = Customer::create(['first_name' => 'Bola', 'last_name' => 'Adebayo', 'email' => 'bola@example.test', 'status' => 'active']);
        $order = Order::create([
            'reference' => 'KAR-TEST-001',
            'customer_id' => $customer->id,
            'channel' => 'consumer',
            'status' => 'confirmed',
            'currency' => 'NGN',
            'total_minor' => 500000,
        ]);

        $booking = Booking::create([
            'order_id' => $order->id,
            'product_type' => 'hotel',
            'provider' => 'test-provider',
            'status' => 'pending',
        ]);

        $this->assertSame($agent->id, $booking->fresh()->assigned_user_id);
        $this->assertNotNull($booking->fresh()->assigned_at);
    }

    public function test_crm_agent_can_prepare_a_pending_flight_invoice_without_creating_a_supplier_pnr(): void
    {
        $agent = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $role = Role::create(['name' => 'booking-agent', 'label' => 'Booking Agent']);
        $permissions = collect(['dashboard.view', 'bookings.view', 'bookings.manage'])->map(
            fn (string $name) => Permission::create(['name' => $name, 'label' => str($name)->headline()])
        );
        $role->permissions()->attach($permissions->pluck('id'));
        $agent->roles()->attach($role);

        $customer = Customer::create([
            'first_name' => 'Ada', 'last_name' => 'Okafor', 'email' => 'ada@example.test',
            'phone' => '+2348012345678', 'status' => 'active',
        ]);

        $search = $this->actingAs($agent)->postJson('/crm/travel/flights/search', [
            'origin' => 'LOS', 'destination' => 'LHR',
            'departure_date' => now()->addWeek()->toDateString(),
            'trip_type' => 'one_way', 'cabin' => 'economy',
            'adults' => 1, 'children' => 0, 'infants' => 0,
            'currency' => 'NGN', 'session_id' => (string) Str::uuid(),
        ])->assertOk();

        $offerId = $search->json('data.offers.0.id');
        $this->postJson("/crm/travel/flights/offers/{$offerId}/revalidate")
            ->assertOk()
            ->assertJsonPath('data.continue_url', route('crm.travel.flights.quote.create', $offerId));

        $this->post("/crm/travel/flights/offers/{$offerId}/invoice", [
            'customer_id' => $customer->id,
            'travellers' => [[
                'type' => 'ADT', 'title' => 'Ms', 'first_name' => 'Ada', 'last_name' => 'Okafor',
                'date_of_birth' => '1990-05-12', 'gender' => 'female', 'nationality' => 'NG',
                'passport_number' => 'A12345678', 'passport_country' => 'NG',
                'passport_expiry' => now()->addYears(3)->toDateString(),
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('orders', ['customer_id' => $customer->id, 'channel' => 'crm', 'status' => 'pending_payment']);
        $this->assertDatabaseHas('bookings', ['travel_offer_id' => $offerId, 'status' => 'pending', 'provider_locator' => null]);
        $this->assertDatabaseHas('payments', ['gateway' => 'invoice', 'status' => 'pending']);
        $this->assertDatabaseHas('crm_tasks', ['customer_id' => $customer->id, 'title' => 'Follow up payment for '.Order::latest('created_at')->value('reference')]);

        $order = Order::query()->latest('created_at')->firstOrFail();
        $this->get(URL::temporarySignedRoute('crm.invoice.pay', now()->addHour(), ['order' => $order]))
            ->assertOk()
            ->assertSee('Review and pay');
    }
}

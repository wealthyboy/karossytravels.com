<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}

<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CrmWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_is_hidden_from_guests(): void
    {
        $this->get('/crm')->assertNotFound();
    }

    public function test_authorized_team_member_can_open_the_crm_dashboard_and_workspace(): void
    {
        $user = User::factory()->create(['account_type' => 'admin']);
        $role = Role::create(['name' => 'crm-operator', 'label' => 'CRM Operator']);
        $permission = Permission::create(['name' => 'dashboard.view', 'label' => 'View dashboard']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->actingAs($user)->get('/crm')->assertOk()->assertSee('Travel CRM');
        $this->actingAs($user)->get('/crm/sales')->assertOk()->assertSee('Sales is being moved into this workspace');
    }
}

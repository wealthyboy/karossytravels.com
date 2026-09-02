<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_route_rejects_a_user_without_the_required_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/analytics')
            ->assertForbidden();
    }

    public function test_admin_route_allows_a_user_with_the_required_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'analyst', 'label' => 'Analyst']);
        $permission = Permission::create(['name' => 'analytics.view', 'label' => 'View Analytics']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get('/admin/analytics')
            ->assertOk();
    }

    public function test_provider_tools_are_visible_only_to_authorized_admin_users(): void
    {
        $this->get('/admin/providers/sabre')->assertNotFound();

        $user = User::factory()->create(['account_type' => 'admin']);
        $role = Role::create(['name' => 'integration-operator', 'label' => 'Integration Operator']);
        $permission = Permission::create(['name' => 'integrations.view', 'label' => 'View Integrations']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get('/admin/providers/sabre')
            ->assertOk()
            ->assertSee('Travel supplier')
            ->assertSee('API Logs')
            ->assertDontSee('access_token');
    }
}

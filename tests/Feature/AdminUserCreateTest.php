<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminUserCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_b2c_user(): void
    {
        $this->actingAs($this->authorizedAdmin())->post('/admin/users', [
            'name' => 'B2C Traveller', 'email' => 'traveller@example.com', 'account_type' => 'b2c',
            'status' => 'active', 'password' => 'Password123', 'password_confirmation' => 'Password123',
        ])->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', ['email' => 'traveller@example.com', 'account_type' => 'b2c', 'company_name' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created']);
    }

    public function test_b2b_user_requires_a_company_name(): void
    {
        $this->actingAs($this->authorizedAdmin())->post('/admin/users', [
            'name' => 'Agency User', 'email' => 'agency@example.com', 'account_type' => 'b2b',
            'status' => 'active', 'password' => 'Password123', 'password_confirmation' => 'Password123',
        ])->assertSessionHasErrors('company_name');
    }

    public function test_b2b_user_receives_only_the_restricted_b2b_role(): void
    {
        $admin = $this->authorizedAdmin();
        $this->seed(RolePermissionSeeder::class);
        $role = Role::create(['name' => 'agency-manager', 'label' => 'Agency Manager']);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Agency User', 'email' => 'agency@example.com', 'account_type' => 'b2b',
            'company_name' => 'Acme Travel', 'status' => 'pending', 'password' => 'Password123',
            'password_confirmation' => 'Password123', 'role_ids' => [$role->id],
        ])->assertRedirect('/admin/users');

        $user = User::where('email', 'agency@example.com')->firstOrFail();
        $this->assertSame('Acme Travel', $user->company_name);
        $this->assertFalse($user->roles()->whereKey($role->id)->exists());
        $this->assertTrue($user->roles()->where('name', 'b2b-agent')->exists());
    }

    public function test_admin_account_can_receive_selected_roles(): void
    {
        $admin = $this->authorizedAdmin();
        $role = Role::create(['name' => 'support-manager', 'label' => 'Support Manager']);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Internal Admin', 'email' => 'internal@example.com', 'account_type' => 'admin',
            'status' => 'active', 'password' => 'Password123',
            'password_confirmation' => 'Password123', 'role_ids' => [$role->id],
        ])->assertRedirect('/admin/users');

        $user = User::where('email', 'internal@example.com')->firstOrFail();
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->roles()->whereKey($role->id)->exists());
    }

    private function authorizedAdmin(): User
    {
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'team.manage', 'label' => 'Manage Team']);
        $role = Role::create(['name' => 'test-admin', 'label' => 'Test Administrator']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}

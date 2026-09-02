<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminRoleCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_create_and_update_a_role_with_permissions(): void
    {
        $admin = $this->authorizedAdmin();
        $viewReports = Permission::create(['name' => 'reports.view', 'label' => 'View Reports']);
        $exportReports = Permission::create(['name' => 'reports.export', 'label' => 'Export Reports']);

        $this->actingAs($admin)->post('/admin/roles', [
            'label' => 'Report Viewer',
            'name' => 'report-viewer',
            'permission_ids' => [$viewReports->id],
        ])->assertRedirect();

        $role = Role::where('name', 'report-viewer')->firstOrFail();
        $this->assertTrue($role->permissions()->whereKey($viewReports->id)->exists());

        $this->actingAs($admin)->put("/admin/roles/{$role->id}", [
            'label' => 'Report Manager',
            'name' => 'report-manager',
            'permission_ids' => [$viewReports->id, $exportReports->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'report-manager', 'label' => 'Report Manager']);
        $this->assertCount(2, $role->fresh()->permissions);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.created', 'subject_id' => (string) $role->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.updated', 'subject_id' => (string) $role->id]);
    }

    public function test_role_key_must_be_a_lowercase_slug(): void
    {
        $this->actingAs($this->authorizedAdmin())->post('/admin/roles', [
            'label' => 'Invalid Role',
            'name' => 'Invalid Role',
        ])->assertSessionHasErrors('name');
    }

    public function test_super_admin_key_is_locked_and_always_has_every_permission(): void
    {
        $admin = $this->authorizedAdmin();
        $extra = Permission::create(['name' => 'reports.export', 'label' => 'Export Reports']);
        $role = Role::create(['name' => 'super-admin', 'label' => 'Super Administrator']);

        $this->actingAs($admin)->put("/admin/roles/{$role->id}", [
            'label' => 'Platform Owner',
            'name' => 'platform-owner',
            'permission_ids' => [],
        ])->assertRedirect();

        $role->refresh();
        $this->assertSame('super-admin', $role->name);
        $this->assertSame('Platform Owner', $role->label);
        $this->assertTrue($role->permissions->contains($extra));
        $this->assertCount(Permission::count(), $role->permissions);
    }

    public function test_built_in_role_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'operations', 'label' => 'Operations']);

        $this->actingAs($this->authorizedAdmin())->delete("/admin/roles/{$role->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_role_assigned_to_a_user_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'support-agent', 'label' => 'Support Agent']);
        $role->users()->attach(User::factory()->create());

        $this->actingAs($this->authorizedAdmin())->delete("/admin/roles/{$role->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_unassigned_custom_role_can_be_deleted(): void
    {
        $role = Role::create(['name' => 'support-agent', 'label' => 'Support Agent']);

        $this->actingAs($this->authorizedAdmin())->delete("/admin/roles/{$role->id}")
            ->assertRedirect('/admin/roles');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.deleted']);
    }

    public function test_bulk_delete_removes_only_unassigned_custom_roles(): void
    {
        $admin = $this->authorizedAdmin();
        $deletable = Role::create(['name' => 'reporter', 'label' => 'Reporter']);
        $assigned = Role::create(['name' => 'support-agent', 'label' => 'Support Agent']);
        $assigned->users()->attach(User::factory()->create());

        $this->actingAs($admin)->delete('/admin/roles/bulk', ['ids' => [$deletable->id, $assigned->id]])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['id' => $deletable->id]);
        $this->assertDatabaseHas('roles', ['id' => $assigned->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.bulk_deleted']);
    }

    public function test_roles_can_be_sorted_by_label(): void
    {
        $admin = $this->authorizedAdmin();
        Role::create(['name' => 'alpha-role', 'label' => 'Alpha Role']);
        Role::create(['name' => 'zulu-role', 'label' => 'Zulu Role']);

        $this->actingAs($admin)->get('/admin/roles?sort=label&direction=desc')
            ->assertOk()
            ->assertSeeInOrder(['Zulu Role', 'Alpha Role']);
    }

    private function authorizedAdmin(): User
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'team.manage'], ['label' => 'Manage Team']);
        $role = Role::create(['name' => 'test-admin-'.uniqid(), 'label' => 'Test Administrator']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}

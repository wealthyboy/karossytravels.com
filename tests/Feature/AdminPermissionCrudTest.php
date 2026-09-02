<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPermissionCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_create_and_update_a_custom_permission(): void
    {
        $admin = $this->authorizedAdmin();

        $this->actingAs($admin)->post('/admin/permissions', [
            'label' => 'Export Reports',
            'name' => 'reports.export',
        ])->assertRedirect();

        $permission = Permission::where('name', 'reports.export')->firstOrFail();

        $this->actingAs($admin)->put("/admin/permissions/{$permission->id}", [
            'label' => 'Download Reports',
            'name' => 'reports.download',
        ])->assertRedirect();

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'label' => 'Download Reports',
            'name' => 'reports.download',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'permission.created', 'subject_id' => (string) $permission->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'permission.updated', 'subject_id' => (string) $permission->id]);
    }

    public function test_permission_name_must_use_resource_action_format(): void
    {
        $this->actingAs($this->authorizedAdmin())->post('/admin/permissions', [
            'label' => 'Invalid Permission',
            'name' => 'Invalid Permission Name',
        ])->assertSessionHasErrors('name');
    }

    public function test_built_in_permission_cannot_be_renamed_or_deleted(): void
    {
        $admin = $this->authorizedAdmin();
        $permission = Permission::create(['label' => 'View Dashboard', 'name' => 'dashboard.view']);

        $this->actingAs($admin)->put("/admin/permissions/{$permission->id}", [
            'label' => 'Dashboard Access',
            'name' => 'dashboard.delete',
        ])->assertRedirect();

        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'dashboard.view', 'label' => 'Dashboard Access']);

        $this->actingAs($admin)->delete("/admin/permissions/{$permission->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
    }

    public function test_assigned_custom_permission_cannot_be_deleted(): void
    {
        $admin = $this->authorizedAdmin();
        $permission = Permission::create(['label' => 'Export Reports', 'name' => 'reports.export']);
        Role::create(['name' => 'reporter', 'label' => 'Reporter'])->permissions()->attach($permission);

        $this->actingAs($admin)->delete("/admin/permissions/{$permission->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
    }

    public function test_unassigned_custom_permission_can_be_deleted(): void
    {
        $admin = $this->authorizedAdmin();
        $permission = Permission::create(['label' => 'Export Reports', 'name' => 'reports.export']);

        $this->actingAs($admin)->delete("/admin/permissions/{$permission->id}")
            ->assertRedirect('/admin/permissions');

        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'permission.deleted']);
    }

    public function test_bulk_delete_removes_only_unassigned_custom_permissions(): void
    {
        $admin = $this->authorizedAdmin();
        $deletable = Permission::create(['label' => 'Export Reports', 'name' => 'reports.export']);
        $assigned = Permission::create(['label' => 'View Reports', 'name' => 'reports.view']);
        Role::create(['name' => 'reporter', 'label' => 'Reporter'])->permissions()->attach($assigned);

        $this->actingAs($admin)->delete('/admin/permissions/bulk', ['ids' => [$deletable->id, $assigned->id]])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('permissions', ['id' => $deletable->id]);
        $this->assertDatabaseHas('permissions', ['id' => $assigned->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'permission.bulk_deleted']);
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

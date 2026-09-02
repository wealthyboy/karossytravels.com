<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class RoleController extends Controller
{
    private const SYSTEM_ROLES = ['super-admin', 'operations', 'analyst'];

    public function index(): View
    {
        $sort = in_array(request('sort'), ['label', 'name', 'permissions_count', 'users_count', 'created_at'], true) ? request('sort') : 'label';
        $direction = request('direction') === 'desc' ? 'desc' : 'asc';
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->when(request('q'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('label', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(20)
            ->withQueryString();

        return view('admin.roles.index', [
            'roles' => $roles,
            'systemRoles' => self::SYSTEM_ROLES,
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', ['permissionGroups' => $this->permissionGroups()]);
    }

    public function store(StoreRoleRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->safe()->except('permission_ids');
        $role = Role::create($data);
        $role->permissions()->sync($request->validated('permission_ids', []));
        $audit->record('role.created', "Created role {$role->name}.", $role, after: ['name' => $role->name, 'label' => $role->label, 'permission_ids' => $role->permissions()->pluck('permissions.id')->all()]);

        return redirect()->route('admin.roles.edit', $role)
            ->with('success', 'Role created successfully.');
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.edit', [
            'role' => $role->load(['permissions', 'users']),
            'permissionGroups' => $this->permissionGroups(),
            'isSystem' => $this->isSystem($role),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role, AuditLogger $audit): RedirectResponse
    {
        $before = ['name' => $role->name, 'label' => $role->label, 'permission_ids' => $role->permissions()->pluck('permissions.id')->all()];
        $data = $request->safe()->except('permission_ids');

        if ($this->isSystem($role)) {
            $data['name'] = $role->name;
        }

        $role->update($data);

        if ($role->name === 'super-admin') {
            $role->permissions()->sync(Permission::pluck('id'));
        } else {
            $role->permissions()->sync($request->validated('permission_ids', []));
        }
        $audit->record('role.updated', "Updated role {$role->name}.", $role, $before, ['name' => $role->name, 'label' => $role->label, 'permission_ids' => $role->permissions()->pluck('permissions.id')->all()]);

        return redirect()->route('admin.roles.edit', $role)
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role, AuditLogger $audit): RedirectResponse
    {
        if ($this->isSystem($role)) {
            return back()->with('error', 'Built-in roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'Remove all users from this role before deleting it.');
        }

        $audit->record('role.deleted', "Deleted role {$role->name}.", $role, ['name' => $role->name, 'label' => $role->label]);
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted successfully.');
    }

    public function bulkDestroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:roles,id'],
        ]);
        $roles = Role::query()->withCount('users')->whereIn('id', $validated['ids'])->get();
        $deletable = $roles->filter(fn (Role $role): bool => ! $this->isSystem($role) && $role->users_count === 0);
        $deletedNames = $deletable->pluck('name')->all();
        Role::whereKey($deletable->pluck('id'))->delete();
        $audit->record('role.bulk_deleted', 'Bulk deleted '.count($deletedNames).' role(s).', after: ['deleted' => $deletedNames]);
        $skipped = $roles->count() - $deletable->count();

        return back()->with('success', $deletable->count().' role(s) deleted.'.($skipped > 0 ? " {$skipped} built-in or assigned role(s) skipped." : ''));
    }

    /** @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, Permission>> */
    private function permissionGroups(): Collection
    {
        return Permission::query()->orderBy('name')->get()
            ->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->headline()->toString());
    }

    private function isSystem(Role $role): bool
    {
        return in_array($role->name, self::SYSTEM_ROLES, true);
    }
}

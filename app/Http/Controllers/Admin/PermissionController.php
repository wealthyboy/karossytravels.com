<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePermissionRequest;
use App\Http\Requests\Admin\UpdatePermissionRequest;
use App\Models\Permission;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PermissionController extends Controller
{
    public function index(): View
    {
        $sort = in_array(request('sort'), ['label', 'name', 'roles_count', 'created_at'], true) ? request('sort') : 'name';
        $direction = request('direction') === 'desc' ? 'desc' : 'asc';
        $permissions = Permission::query()
            ->withCount('roles')
            ->when(request('q'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('label', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(20)
            ->withQueryString();

        return view('admin.permissions.index', [
            'permissions' => $permissions,
            'systemPermissions' => $this->systemPermissions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.permissions.create');
    }

    public function store(StorePermissionRequest $request, AuditLogger $audit): RedirectResponse
    {
        $permission = Permission::create($request->validated());
        $audit->record('permission.created', "Created permission {$permission->name}.", $permission, after: $permission->only(['name', 'label']));

        return redirect()->route('admin.permissions.edit', $permission)
            ->with('success', 'Permission created successfully.');
    }

    public function edit(Permission $permission): View
    {
        return view('admin.permissions.edit', [
            'permission' => $permission->load('roles'),
            'isSystem' => $this->isSystem($permission),
        ]);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission, AuditLogger $audit): RedirectResponse
    {
        $before = $permission->only(['name', 'label']);
        $data = $request->validated();

        if ($this->isSystem($permission)) {
            $data['name'] = $permission->name;
        }

        $permission->update($data);
        $audit->record('permission.updated', "Updated permission {$permission->name}.", $permission, $before, $permission->only(['name', 'label']));

        return redirect()->route('admin.permissions.edit', $permission)
            ->with('success', 'Permission updated successfully.');
    }

    public function destroy(Permission $permission, AuditLogger $audit): RedirectResponse
    {
        if ($this->isSystem($permission)) {
            return back()->with('error', 'Built-in permissions cannot be deleted.');
        }

        if ($permission->roles()->exists()) {
            return back()->with('error', 'Remove this permission from all roles before deleting it.');
        }

        $audit->record('permission.deleted', "Deleted permission {$permission->name}.", $permission, $permission->only(['name', 'label']));
        $permission->delete();

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Permission deleted successfully.');
    }

    public function bulkDestroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);
        $systemPermissions = $this->systemPermissions();
        $permissions = Permission::query()->withCount('roles')->whereIn('id', $validated['ids'])->get();
        $deletable = $permissions->filter(fn (Permission $permission): bool => ! in_array($permission->name, $systemPermissions, true) && $permission->roles_count === 0);
        $deletedNames = $deletable->pluck('name')->all();
        Permission::whereKey($deletable->pluck('id'))->delete();
        $audit->record('permission.bulk_deleted', 'Bulk deleted '.count($deletedNames).' permission(s).', after: ['deleted' => $deletedNames]);
        $skipped = $permissions->count() - $deletable->count();

        return back()->with('success', $deletable->count().' permission(s) deleted.'.($skipped > 0 ? " {$skipped} protected or assigned permission(s) skipped." : ''));
    }

    /** @return array<int, string> */
    private function systemPermissions(): array
    {
        return array_column(AdminPermission::cases(), 'value');
    }

    private function isSystem(Permission $permission): bool
    {
        return in_array($permission->name, $this->systemPermissions(), true);
    }
}

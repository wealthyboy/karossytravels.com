@extends('layouts.admin')

@section('title', 'Roles')

@section('content')
@php
    $sortUrl = fn (string $column) => route('admin.roles.index', array_merge(request()->query(), ['sort' => $column, 'direction' => request('sort') === $column && request('direction') !== 'desc' ? 'desc' : 'asc']));
    $sortIcon = fn (string $column) => request('sort') === $column ? (request('direction') === 'desc' ? 'bi-sort-down' : 'bi-sort-up') : 'bi-arrow-down-up';
@endphp
<header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"><div><p class="text-danger fw-semibold mb-1">ACCESS CONTROL</p><h1 class="h3 fw-bold mb-1">Roles</h1><p class="text-secondary mb-0">Group permissions into reusable access levels for administrators.</p></div><a href="{{ route('admin.roles.create') }}" class="btn btn-karossy"><i class="bi bi-plus-lg me-2"></i>New role</a></header>

<form id="role-bulk-form" method="POST" action="{{ route('admin.roles.bulk-destroy') }}" onsubmit="return confirm('Delete the selected roles? Built-in and assigned roles will be skipped.')">@csrf @method('DELETE')</form>
<div class="card content-card admin-table-card">
    <div class="card-header bg-white border-0 p-3 p-md-4"><div class="d-flex flex-column flex-md-row justify-content-between gap-3"><form method="GET" class="d-flex gap-2 flex-grow-1" autocomplete="off"><div class="input-group admin-table-search"><span class="input-group-text"><i class="bi bi-search"></i></span><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search role name or key" aria-label="Search roles" autocomplete="off"></div><button class="btn admin-search-button">Search</button>@if(request('q'))<a href="{{ route('admin.roles.index') }}" class="btn btn-link text-secondary">Clear</a>@endif</form><button type="submit" form="role-bulk-form" class="btn btn-outline-danger bulk-delete-button" disabled><i class="bi bi-trash3 me-2"></i>Delete selected <span class="selected-count"></span></button></div></div>
    <div class="table-responsive"><table class="table admin-data-table align-middle mb-0"><thead><tr><th class="table-check ps-4"><input class="form-check-input select-all-rows" type="checkbox" aria-label="Select all deletable roles"></th><th><a href="{{ $sortUrl('label') }}">Role <i class="bi {{ $sortIcon('label') }}"></i></a></th><th><a href="{{ $sortUrl('name') }}">Key <i class="bi {{ $sortIcon('name') }}"></i></a></th><th>Type</th><th><a href="{{ $sortUrl('permissions_count') }}">Permissions <i class="bi {{ $sortIcon('permissions_count') }}"></i></a></th><th><a href="{{ $sortUrl('users_count') }}">Users <i class="bi {{ $sortIcon('users_count') }}"></i></a></th><th class="text-end pe-4">Actions</th></tr></thead><tbody>
    @forelse($roles as $role)
        @php($isSystem = in_array($role->name, $systemRoles, true)) @php($canDelete = !$isSystem && $role->users_count === 0)
        <tr><td class="table-check ps-4"><input class="form-check-input row-checkbox" form="role-bulk-form" name="ids[]" value="{{ $role->id }}" type="checkbox" aria-label="Select {{ $role->label }}"></td><td><strong>{{ $role->label }}</strong></td><td><code class="permission-key">{{ $role->name }}</code></td><td><span class="badge rounded-pill {{ $isSystem ? 'badge-system' : 'badge-custom' }}">{{ $isSystem ? 'Built-in' : 'Custom' }}</span></td><td><span class="count-pill">{{ $role->permissions_count }}</span></td><td><span class="count-pill">{{ $role->users_count }}</span></td><td class="text-end pe-4"><div class="table-actions"><a href="{{ route('admin.roles.edit', $role) }}" class="btn table-action-btn" title="Edit"><i class="bi bi-pencil"></i><span>Edit</span></a>@if($canDelete)<form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete this role?')">@csrf @method('DELETE')<button class="btn table-action-btn table-action-danger" title="Delete"><i class="bi bi-trash3"></i><span>Delete</span></button></form>@endif</div></td></tr>
    @empty<tr><td colspan="7" class="text-center text-secondary py-5"><i class="bi bi-person-badge d-block fs-2 mb-2"></i>No roles found.</td></tr>@endforelse
    </tbody></table></div>
    <div class="card-footer bg-white border-0 p-3 p-md-4 d-flex justify-content-between align-items-center"><small class="text-secondary">Showing {{ $roles->firstItem() ?? 0 }}–{{ $roles->lastItem() ?? 0 }} of {{ $roles->total() }}</small>{{ $roles->links() }}</div>
</div>
@endsection

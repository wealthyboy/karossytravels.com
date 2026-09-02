@extends('layouts.admin')

@section('title', 'Edit Permission')

@section('content')
<header class="mb-4"><a href="{{ route('admin.permissions.index') }}" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Permissions</a><p class="text-danger fw-semibold mt-3 mb-1">ACCESS CONTROL</p><h1 class="h3 fw-bold mb-2">Edit permission</h1><p class="text-secondary mb-0">Update how this capability appears in access-control screens.</p></header>
<div class="row g-4"><div class="col-xl-8"><div class="card content-card"><div class="card-body p-4 p-lg-5"><form method="POST" action="{{ route('admin.permissions.update', $permission) }}">@csrf @method('PUT') @include('admin.permissions._form')<div class="d-flex gap-2"><button class="btn btn-karossy">Save changes</button><a href="{{ route('admin.permissions.index') }}" class="btn btn-outline-secondary">Cancel</a></div></form></div></div></div>
<aside class="col-xl-4"><div class="card content-card"><div class="card-body p-4"><h2 class="h6">Assignment</h2><p class="small text-secondary">This permission is currently assigned to {{ $permission->roles->count() }} role(s).</p>@forelse($permission->roles as $role)<span class="badge text-bg-light border me-1 mb-1">{{ $role->label }}</span>@empty<span class="small text-secondary">Not assigned to a role.</span>@endforelse</div></div></aside></div>
@endsection

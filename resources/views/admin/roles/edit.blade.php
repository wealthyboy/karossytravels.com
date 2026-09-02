@extends('layouts.admin')

@section('title', 'Edit Role')

@section('content')
<header class="mb-4"><a href="{{ route('admin.roles.index') }}" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Roles</a><p class="text-danger fw-semibold mt-3 mb-1">ACCESS CONTROL</p><h1 class="h3 fw-bold mb-2">Edit role</h1><p class="text-secondary mb-0">Update this role and the capabilities it grants.</p></header>
<div class="card content-card"><div class="card-body p-4 p-lg-5"><form method="POST" action="{{ route('admin.roles.update', $role) }}">@csrf @method('PUT') @include('admin.roles._form')<div class="d-flex justify-content-between flex-wrap gap-3 border-top mt-4 pt-4"><div><button class="btn btn-karossy">Save changes</button><a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancel</a></div><span class="text-secondary small align-self-center"><i class="bi bi-people me-1"></i>{{ $role->users->count() }} assigned user(s)</span></div></form></div></div>
@endsection

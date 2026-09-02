@extends('layouts.admin')

@section('title', 'New Role')

@section('content')
<header class="mb-4"><a href="{{ route('admin.roles.index') }}" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Roles</a><p class="text-danger fw-semibold mt-3 mb-1">ACCESS CONTROL</p><h1 class="h3 fw-bold mb-2">Create role</h1><p class="text-secondary mb-0">Create an access level and assign its permissions.</p></header>
<div class="card content-card"><div class="card-body p-4 p-lg-5"><form method="POST" action="{{ route('admin.roles.store') }}">@csrf @include('admin.roles._form')<div class="d-flex gap-2 border-top mt-4 pt-4"><button class="btn btn-karossy">Create role</button><a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancel</a></div></form></div></div>
@endsection

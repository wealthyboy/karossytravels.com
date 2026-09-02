@extends('layouts.admin')

@section('title', 'New Permission')

@section('content')
<header class="mb-4"><a href="{{ route('admin.permissions.index') }}" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Permissions</a><p class="text-danger fw-semibold mt-3 mb-1">ACCESS CONTROL</p><h1 class="h3 fw-bold mb-2">Create permission</h1><p class="text-secondary mb-0">Add an access capability that can be assigned to roles.</p></header>
<div class="card content-card"><div class="card-body p-4 p-lg-5"><form method="POST" action="{{ route('admin.permissions.store') }}">@csrf @include('admin.permissions._form')<div class="d-flex gap-2"><button class="btn btn-karossy">Create permission</button><a href="{{ route('admin.permissions.index') }}" class="btn btn-outline-secondary">Cancel</a></div></form></div></div>
@endsection

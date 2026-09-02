@extends('layouts.admin')
@section('title', 'Edit Fare Rule')
@section('content')
<header class="mb-4"><p class="text-danger fw-semibold mb-1">FLIGHTS</p><h1 class="h3 fw-bold mb-1">Edit fare rule</h1><p class="text-secondary mb-0">Checkout always uses the currently active rule version.</p></header>
<form method="POST" action="{{ route('admin.fair-rules.update', $rule) }}" class="card content-card"><div class="card-body p-4 p-lg-5">@csrf @method('PUT') @include('admin.fair_rules._form')</div><div class="card-footer bg-white border-0 px-4 px-lg-5 pb-4 d-flex gap-2"><button class="btn btn-karossy" type="submit"><i class="bi bi-check2"></i> Update rule</button><a class="btn btn-outline-secondary" href="{{ route('admin.fair-rules.index') }}">Back</a></div></form>
@endsection

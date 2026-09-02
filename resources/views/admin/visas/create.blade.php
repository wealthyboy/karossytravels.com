@extends('layouts.admin')

@section('title', 'New visa service')

@section('content')
<header class="mb-4">
    <a href="{{ route('admin.visas.index') }}" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Visa services</a>
    <p class="text-danger fw-semibold mt-3 mb-1">VISA OPERATIONS</p>
    <h1 class="h3 fw-bold mb-2">New visa service</h1>
    <p class="text-secondary mb-0">Add a destination, processing duration, customer fee and application requirements.</p>
</header>

@if($errors->any())<div class="alert alert-danger"><strong>Please correct the form.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('admin.visas.store') }}">@csrf @include('admin.visas._form')</form>
@endsection

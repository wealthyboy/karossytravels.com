@extends('layouts.admin')
@section('title', 'New Customer')
@section('content')
<header class="mb-4"><p class="text-danger fw-semibold mb-1">CUSTOMER MANAGEMENT</p><h1 class="h3 fw-bold mb-2">New customer</h1><p class="text-secondary mb-0">Create a customer profile for bookings made by staff, B2B partners or the customer.</p></header>
@if($errors->any())<div class="alert alert-danger"><strong>Please correct the form.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('admin.customers.store') }}">@csrf @include('admin.customers._form')</form>
@endsection

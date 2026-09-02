@extends('layouts.admin')
@section('title', 'New flight offer')
@section('content')
<header class="admin-page-heading"><a class="service-back" href="{{ route('admin.flight-offers.index') }}"><i class="bi bi-arrow-left"></i> Flight offers</a><span class="admin-eyebrow mt-3">HOMEPAGE DEALS</span><h1>New flight offer</h1><p>Add a real route, airline, travel window and indicative starting price.</p></header>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" enctype="multipart/form-data" action="{{ route('admin.flight-offers.store') }}">@csrf @include('admin.flight-offers._form')</form>
@endsection

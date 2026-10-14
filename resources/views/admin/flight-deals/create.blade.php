@extends('layouts.admin')
@section('title', 'New flight deal')
@section('content')<header class="admin-page-heading"><a class="service-back" href="{{ route('admin.flight-deals.index') }}"><i class="bi bi-arrow-left"></i> Flight deals</a><span class="admin-eyebrow mt-3">PRICING</span><h1>New flight deal</h1><p>Discount a route for selected airlines or all airlines, within clear date windows.</p></header>@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif<form method="POST" action="{{ route('admin.flight-deals.store') }}">@csrf @include('admin.flight-deals._form')</form>
@endsection

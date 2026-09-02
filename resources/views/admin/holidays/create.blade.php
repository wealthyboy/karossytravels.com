@extends('layouts.admin')
@section('title','New holiday package')
@section('content')
<header class="admin-page-heading"><a class="service-back" href="{{ route('admin.holidays.index') }}"><i class="bi bi-arrow-left"></i> Holiday packages</a><span class="admin-eyebrow mt-3">CURATED TRAVEL</span><h1>New holiday package</h1><p>Publish destination imagery, pricing, travel dates and everything included.</p></header>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif<form method="POST" enctype="multipart/form-data" action="{{ route('admin.holidays.store') }}">@csrf @include('admin.holidays._form')</form>
@endsection

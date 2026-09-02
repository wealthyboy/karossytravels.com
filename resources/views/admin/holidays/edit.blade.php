@extends('layouts.admin')
@section('title','Edit '.$holidayPackage->title)
@section('content')
<header class="admin-page-heading"><a class="service-back" href="{{ route('admin.holidays.index') }}"><i class="bi bi-arrow-left"></i> Holiday packages</a><span class="admin-eyebrow mt-3">CURATED TRAVEL</span><h1>Edit holiday package</h1><p>{{ $holidayPackage->title }}</p></header>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif<form method="POST" enctype="multipart/form-data" action="{{ route('admin.holidays.update',$holidayPackage) }}">@csrf @method('PUT') @include('admin.holidays._form')</form>
@endsection

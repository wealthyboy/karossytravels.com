@extends('layouts.admin')
@section('title', 'New Add-on')
@section('content')
<header class="mb-4"><p class="text-danger fw-semibold mb-1">CHECKOUT SERVICES</p><h1 class="h3 fw-bold mb-1">New add-on</h1><p class="text-secondary mb-0">Offer an optional service during flight or hotel checkout.</p></header>
<form method="POST" enctype="multipart/form-data" action="{{ route('admin.addons.store') }}" class="card content-card"><div class="card-body p-4 p-lg-5">@csrf @include('admin.addons._form')</div><div class="card-footer bg-white border-0 px-4 px-lg-5 pb-4 d-flex gap-2"><button class="btn btn-karossy" type="submit"><i class="bi bi-check2"></i> Save add-on</button><a class="btn btn-outline-secondary" href="{{ route('admin.addons.index',['type'=>$type]) }}">Cancel</a></div></form>
@endsection

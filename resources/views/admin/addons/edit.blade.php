@extends('layouts.admin')
@section('title', 'Edit Add-on')
@section('content')
<header class="mb-4"><p class="text-danger fw-semibold mb-1">CHECKOUT SERVICES</p><h1 class="h3 fw-bold mb-1">Edit add-on</h1><p class="text-secondary mb-0">Update availability, customer-facing copy and price.</p></header>
<form method="POST" enctype="multipart/form-data" action="{{ route('admin.addons.update',$addon) }}" class="card content-card"><div class="card-body p-4 p-lg-5">@csrf @method('PUT') @include('admin.addons._form',['type'=>$addon->type])</div><div class="card-footer bg-white border-0 px-4 px-lg-5 pb-4 d-flex gap-2"><button class="btn btn-karossy" type="submit"><i class="bi bi-check2"></i> Update add-on</button><a class="btn btn-outline-secondary" href="{{ route('admin.addons.index',['type'=>$addon->type]) }}">Back</a></div></form>
@endsection

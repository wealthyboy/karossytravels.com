@extends('layouts.admin')

@section('title', ucfirst($product).' Markup')

@section('content')
<header class="mb-4"><p class="text-danger fw-semibold mb-1">PRICING</p><h1 class="h3 fw-bold mb-2">{{ ucfirst($product) }} default markup</h1><p class="text-secondary mb-0">Applied to every {{ $product }} offer before currency conversion. Leave the value empty or set it to zero to show the base price unchanged.</p></header>

<div class="card content-card"><div class="card-body p-4 p-lg-5">
    <form method="POST" action="{{ route('admin.pricing.update', $product) }}" class="admin-form" data-loading-form novalidate>@csrf @method('PUT')
        <div class="row g-4">
            <div class="col-md-6"><label class="form-label" for="markup_type">Markup method</label><select class="form-select @error('markup_type') is-invalid @enderror" id="markup_type" name="markup_type"><option value="percentage" @selected(old('markup_type', $setting->markup_type) === 'percentage')>Percentage</option><option value="fixed" @selected(old('markup_type', $setting->markup_type) === 'fixed')>Fixed amount</option></select>@error('markup_type')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="markup_value">Markup value</label><div class="input-group"><input class="form-control @error('markup_value') is-invalid @enderror" id="markup_value" name="markup_value" type="number" min="0" step="0.01" value="{{ old('markup_value', $setting->markup_value) }}" placeholder="No markup"><span class="input-group-text">value</span>@error('markup_value')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="form-text">For percentage, enter 5 for 5%. For fixed, enter the amount in the selected currency.</div></div>
            <div class="col-md-6"><label class="form-label" for="currency">Fixed-markup currency</label><select class="form-select @error('currency') is-invalid @enderror" id="currency" name="currency"><option value="USD" @selected(old('currency', $setting->currency) === 'USD')>USD — US Dollar</option><option value="NGN" @selected(old('currency', $setting->currency) === 'NGN')>NGN — Nigerian Naira</option></select>@error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch mb-2"><input type="hidden" name="enabled" value="0"><input class="form-check-input" id="enabled" name="enabled" type="checkbox" value="1" @checked(old('enabled', $setting->enabled))><label class="form-check-label" for="enabled">Enable this pricing rule</label></div></div>
        </div>
        <div class="d-flex justify-content-end mt-4"><button class="btn btn-karossy" type="submit" data-submit-loading><span data-submit-label>Save pricing</span><span class="spinner-border spinner-border-sm d-none" data-submit-spinner></span></button></div>
    </form>
</div></div>
@endsection

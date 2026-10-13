@extends('layouts.admin')

@section('title', ucfirst($product).' Pricing')

@section('content')
@php
    $legacyFixed = $setting->markup_type !== 'percentage';
    $percent = old('markup_value', $legacyFixed || $setting->markup_value === null ? null : (float) $setting->markup_value);
    $direction = old('adjustment_type', $setting->adjustment_type ?? 'markup');
@endphp
<header class="mb-4">
    <p class="text-danger fw-semibold mb-1">PRICING</p>
    <h1 class="h3 fw-bold mb-2">{{ $product === 'airline' ? 'Flight' : 'Hotel' }} markup &amp; markdown</h1>
    <p class="text-secondary mb-0">Increase or decrease each supplier price by a percentage before currency conversion. The same percentage applies in every customer currency.</p>
</header>
@if($legacyFixed)
    <div class="alert alert-warning" role="alert">Your previously saved fixed-amount rule is no longer applied. Enter a percentage and save to replace it. Its old amount has not been converted into a percentage.</div>
@endif
<div class="card content-card"><div class="card-body p-4 p-lg-5">
    <form method="POST" action="{{ route('admin.pricing.update', $product) }}" class="admin-form" data-loading-form data-default-pricing-form novalidate>
        @csrf @method('PUT')
        <input type="hidden" name="markup_type" value="percentage">
        <div class="row g-4">
            <div class="col-md-6">
                <label class="form-label" for="adjustment_type">Price adjustment</label>
                <select class="form-select @error('adjustment_type') is-invalid @enderror" id="adjustment_type" name="adjustment_type" data-pricing-direction>
                    <option value="markup" @selected($direction === 'markup')>Markup — increase the price</option>
                    <option value="markdown" @selected($direction === 'markdown')>Markdown — decrease the price</option>
                </select>
                @error('adjustment_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Percentage only. No fixed amount or currency selection is needed.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="markup_value">Adjustment percentage</label>
                <div class="input-group">
                    <input class="form-control @error('markup_value') is-invalid @enderror" id="markup_value" name="markup_value" type="number" min="0" max="{{ $direction === 'markdown' ? '99.99' : '1000000' }}" step="0.01" value="{{ $percent }}" placeholder="0" aria-describedby="pricing-percentage-help" data-pricing-percent>
                    <span class="input-group-text">%</span>
                    @error('markup_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @error('markup_type')<div class="text-danger small mt-1 w-100">{{ $message }}</div>@enderror
                </div>
                <div class="form-text" id="pricing-percentage-help">Enter 10 for 10%. Blank or zero leaves the supplier price unchanged. Markdown must be less than 100%.</div>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="enabled" value="0">
                    <input class="form-check-input" id="enabled" name="enabled" type="checkbox" value="1" @checked(old('enabled', $setting->enabled)) data-pricing-enabled>
                    <label class="form-check-label" for="enabled">Enable this pricing rule</label>
                </div>
                <div class="form-text">When switched off, no default markup or markdown is applied.@if($product === 'airline') Existing flight deals are managed separately.@endif</div>
            </div>
            <div class="col-12">
                <div class="rounded-3 border bg-light p-3" aria-live="polite" aria-atomic="true">
                    <span class="small text-secondary d-block mb-1">EXAMPLE · BASE PRICE 100,000 IN ANY CURRENCY</span>
                    <strong data-pricing-example>{{ $percent > 0 && $setting->enabled ? 'Save your percentage to adjust supplier prices.' : 'No adjustment: 100,000 → 100,000' }}</strong>
                    <p class="small text-secondary mb-0 mt-2">Applied once to each offer's total, not once per traveller or hotel night. Hotel nightly prices reflect the adjusted stay total. New quotes use this rule; completed bookings are not changed.</p>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end mt-4"><button class="btn btn-karossy" type="submit" data-submit-loading><span data-submit-label>Save pricing</span><span class="spinner-border spinner-border-sm d-none" data-submit-spinner></span></button></div>
    </form>
</div></div>
@endsection

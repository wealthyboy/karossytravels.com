@extends('layouts.admin')

@section('title', 'Search Hotels')

@section('content')
<header class="mb-4"><p class="text-danger fw-semibold mb-1">HOTEL OPERATIONS</p><h1 class="h3 fw-bold mb-1">Search hotels</h1><p class="text-secondary mb-0">Search and price live hotel rates for Karossy customers and partners.</p></header>

<section class="admin-travel-search-card mb-4"><x-hotel-search-form :check-in="$defaultCheckIn" :check-out="$defaultCheckOut" :action="route('admin.hotels.results')" submit-label="Search hotels" /></section>

@if(is_array($offers))
    @php $money = fn (int $minor, string $code) => \App\Support\CurrencyMetadata::format($minor, $code); @endphp
    <div class="d-flex justify-content-between align-items-end mb-3"><div><h2 class="h5 mb-1">Available hotels</h2><p class="small text-secondary mb-0">{{ count($offers) }} live {{ str('property')->plural(count($offers)) }} · Hotel markup and {{ $currency }} conversion applied</p></div></div>
    <div class="admin-hotel-results">
        @forelse($offers as $offer)
            <article class="admin-hotel-result"><div class="admin-hotel-result-icon"><i class="bi bi-building"></i></div><div class="admin-hotel-result-copy"><div class="d-flex align-items-center gap-2"><h3>{{ $offer['name'] }}</h3>@if($offer['rating'])<span class="badge text-bg-dark">{{ number_format($offer['rating'], 1) }}</span>@endif</div><p><i class="bi bi-geo-alt"></i> {{ data_get($offer, 'location.address') }}, {{ data_get($offer, 'location.city') }}</p><small>{{ $offer['room_name'] }} · {{ $offer['rate_name'] }} @if($offer['breakfast_included'])· Breakfast included @endif</small></div><div class="admin-hotel-result-price"><small>Customer total</small><strong>{{ $money($offer['price']['total_minor'], $offer['price']['currency']) }}</strong><span>{{ $money($offer['price']['nightly_minor'], $offer['price']['currency']) }} / night</span><a href="{{ route('admin.hotels.orders.create', $offer['id']) }}" class="btn btn-karossy btn-sm">Select</a></div></article>
        @empty
            <div class="alert alert-light border">No hotel offers were returned for this search.</div>
        @endforelse
    </div>
@endif
@endsection

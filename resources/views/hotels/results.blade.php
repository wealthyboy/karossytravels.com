@extends('layouts.public')

@section('title', 'Hotel results')

@section('content')
@php
    $money = fn (int $minor, string $code) => \App\Support\CurrencyMetadata::format($minor, $code);
@endphp
<section class="results-search-strip"><div class="container public-container">
    <div class="results-search-summary"><div><small>Destination</small><strong>{{ $criteria['destination_label'] }}</strong></div><div class="results-summary-divider"></div><div><small>Stay dates</small><strong>{{ \Carbon\Carbon::parse($criteria['check_in'])->format('M j') }} – {{ \Carbon\Carbon::parse($criteria['check_out'])->format('M j') }}</strong></div><div><small>Guests</small><strong>{{ $criteria['adults'] + $criteria['children'] }} travellers · {{ $criteria['rooms'] }} {{ str('room')->plural($criteria['rooms']) }}</strong></div><button type="button" class="btn btn-outline-dark" data-bs-toggle="collapse" data-bs-target="#inlineHotelSearch" aria-expanded="false" aria-controls="inlineHotelSearch"><i class="bi bi-pencil"></i> Edit search</button></div>
    <div class="collapse results-inline-search results-inline-hotel-search" id="inlineHotelSearch"><x-hotel-search-form :check-in="$criteria['check_in']" :check-out="$criteria['check_out']" :criteria="$criteria" :action="route('hotels.results')" submit-label="Update search" /></div>
</div></section>

<section class="flight-results-page hotel-results-page"><div class="container public-container">
    <div class="results-heading"><div><span class="public-eyebrow">Find your stay</span><h1>Hotels in {{ $criteria['destination_label'] }}</h1><p>{{ count($offers) }} live {{ str('property')->plural(count($offers)) }} · Prices include taxes and configured Karossy hotel markup</p></div><label class="results-sort">Sort by<select class="form-select"><option>Recommended</option><option>Lowest price</option><option>Highest rating</option></select></label></div>
    <div class="row g-4"><aside class="col-lg-3"><div class="results-filter-card"><div class="d-flex justify-content-between align-items-center"><h2>Filter properties</h2><button type="button">Reset</button></div><div class="filter-group"><strong>Guest rating</strong><label><input class="form-check-input" type="checkbox"> 4 stars and above</label><label><input class="form-check-input" type="checkbox"> 3 stars and above</label></div><div class="filter-group"><strong>Popular amenities</strong><label><input class="form-check-input" type="checkbox"> Breakfast included</label><label><input class="form-check-input" type="checkbox"> Refundable</label></div><div class="filter-group"><strong>Display currency</strong><small>{{ $currency }} selected for your location</small></div></div></aside>
        <div class="col-lg-9"><div class="hotel-offer-list">
        @forelse($offers as $offer)
            <article class="hotel-offer-card"><div class="hotel-offer-image"><i class="bi bi-building"></i><span>Karossy verified stay</span></div><div class="hotel-offer-content"><div class="hotel-offer-copy"><div class="hotel-rating">@if($offer['rating'])<strong>{{ number_format($offer['rating'], 1) }}</strong><span>Property rating</span>@endif</div><h2>{{ $offer['name'] }}</h2><p class="hotel-location"><i class="bi bi-geo-alt"></i> {{ data_get($offer, 'location.address') }}, {{ data_get($offer, 'location.city') }}</p><div class="hotel-amenities">@foreach(array_slice($offer['amenities'], 0, 4) as $amenity)<span><i class="bi bi-check2"></i>{{ $amenity }}</span>@endforeach</div><div class="hotel-rate-details"><strong>{{ $offer['room_name'] ?: 'Available room' }}</strong><span>{{ $offer['rate_name'] ?: 'Best available rate' }}</span>@if($offer['breakfast_included'])<span class="text-success"><i class="bi bi-cup-hot"></i> Breakfast included</span>@endif<span class="{{ $offer['refundable'] ? 'text-success' : 'text-secondary' }}"><i class="bi {{ $offer['refundable'] ? 'bi-check-circle' : 'bi-info-circle' }}"></i> {{ $offer['refundable'] ? 'Refundable' : 'Cancellation rules apply' }}</span></div></div><div class="hotel-offer-price"><small>Per night</small><strong>{{ $money($offer['price']['nightly_minor'], $offer['price']['currency']) }}</strong><span>{{ $money($offer['price']['total_minor'], $offer['price']['currency']) }} total</span><small>Taxes and fees included</small><button class="btn btn-karossy" type="button" disabled>View rooms</button></div></div></article>
        @empty
            <div class="booking-card text-center"><i class="bi bi-building fs-1 text-secondary"></i><h2 class="h5 mt-3">No hotels found</h2><p class="text-secondary">Try another destination or different dates.</p><button class="btn btn-karossy" type="button" data-bs-toggle="collapse" data-bs-target="#inlineHotelSearch" aria-expanded="false" aria-controls="inlineHotelSearch">Change search</button></div>
        @endforelse
        </div></div>
    </div>
</div></section>
@endsection

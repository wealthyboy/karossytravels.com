@extends('layouts.public')

@section('title', 'Flight results')
@section('meta_description', 'Compare live domestic and international flight options, fares and schedules with Karossy Travels.')
@section('robots', 'noindex,follow')

@section('content')
@php
    $segments = $criteria['segments'] ?? [];
    $origin = $criteria['origin'] ?? data_get($segments, '0.origin', '');
    $destination = $criteria['destination'] ?? data_get($segments, (count($segments) - 1).'.destination', '');
    $departureDate = $criteria['departure_date'] ?? data_get($segments, '0.departure_date');
    $returnDate = $criteria['return_date'] ?? data_get($segments, (count($segments) - 1).'.departure_date', $departureDate);
@endphp

<section class="results-search-strip">
    <div class="container public-container">
        <div class="results-search-summary">
            <div><small>From</small><strong>{{ $origin }}</strong></div>
            <i class="bi bi-arrow-right"></i>
            <div><small>To</small><strong>{{ $destination }}</strong></div>
            <div class="results-summary-divider"></div>
            <div>
                <small>Travel dates</small>
                <strong>{{ \Carbon\Carbon::parse($departureDate)->format('M j') }}@if($returnDate && $returnDate !== $departureDate) – {{ \Carbon\Carbon::parse($returnDate)->format('M j') }}@endif</strong>
            </div>
            <div><small>Travellers</small><strong>{{ $criteria['adults'] }} {{ str('adult')->plural($criteria['adults']) }} · {{ str($criteria['cabin'])->replace('_', ' ')->headline() }}</strong></div>
            <button type="button" class="btn btn-outline-dark" data-bs-toggle="collapse" data-bs-target="#inlineFlightSearch" aria-expanded="false" aria-controls="inlineFlightSearch"><i class="bi bi-pencil"></i> Edit search</button>
        </div>
        <div class="collapse results-inline-search" id="inlineFlightSearch">
            <x-flight-search-form :departure-date="$departureDate" :return-date="$returnDate" :criteria="$criteria" :show-results="false" submit-label="Update search" />
        </div>
    </div>
</section>

<section class="flight-results-page public-live-flight-results">
    <div class="container public-flight-results-container"
         data-public-flight-results-page
         data-search-url="{{ route('flights.search.store') }}"
         data-charter-contact="{{ config('travel.support.email') }}"
         data-review-url-template="{{ route('flights.review', ['offer' => '__OFFER__']) }}"
         data-revalidate-url-template="{{ route('flights.offers.revalidate', ['offer' => '__OFFER__']) }}">
        <script type="application/json" data-flight-search-criteria>@json($criteria)</script>

        @if(session('warning'))<div class="alert alert-warning mb-3"><i class="bi bi-clock-history me-2"></i>{{ session('warning') }}</div>@endif

        <div class="public-flight-results-header">
            <div class="public-results-heading">
                <span class="public-eyebrow">Choose your flight</span>
                <h1>{{ $origin }} to {{ $destination }}</h1>
                <p>Searching live airline availability · Taxes and fees included</p>
            </div>
            <div class="public-flight-results-overview" data-flight-results-overview></div>
        </div>

        <div class="flight-search-message d-none" role="alert"></div>
        <section class="flight-results d-none" aria-live="polite"></section>
    </div>
</section>

<div class="modal fade public-flight-search-modal" id="publicFlightSearchModal" tabindex="-1" aria-labelledby="publicFlightSearchModalTitle" aria-describedby="publicFlightSearchStatus" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body public-flight-search-promo">
                <div class="public-flight-search-promo-media">
                    <img src="{{ asset('images/gyro-air/aircraft.jpg') }}" alt="Gyro Air private jet parked on the apron" width="2364" height="1578" decoding="async">
                    <div class="public-flight-search-promo-shade" aria-hidden="true"></div>
                    <span class="public-flight-search-sponsored">Karossy charter partner</span>
                    <div class="public-flight-search-promo-brand"><strong>GYRO AIR</strong><small>Private flights, planned around you.</small></div>
                    <div class="public-flight-loader-image-progress">
                        <div class="public-flight-loader-progress-meta"><span><i aria-hidden="true"></i><strong data-public-search-phase>Search in progress</strong></span><small>Live fares &amp; availability</small></div>
                        <div class="public-search-progress public-flight-loader-progress" role="progressbar" aria-label="Flight search in progress" aria-valuetext="Searching live airline availability" data-public-flight-progress><span></span></div>
                    </div>
                </div>
                <div class="public-flight-search-promo-copy">
                    <div class="public-flight-loader-heading">
                        <div><span class="modal-eyebrow">Live flight search</span><h2 id="publicFlightSearchModalTitle">Finding your best flights</h2></div>
                        <span class="public-flight-loader-route" data-public-search-route>{{ $origin }} → {{ $destination }}</span>
                    </div>
                    <p id="publicFlightSearchStatus" data-public-search-status role="status" aria-live="polite" aria-atomic="true">Checking live fares from {{ $origin }} to {{ $destination }}…</p>
                    <div class="public-flight-loader-note"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Please keep this page open. Your flight options will appear when the search finishes.</span></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

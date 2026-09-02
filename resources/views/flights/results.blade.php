@extends('layouts.public')

@section('title', 'Flight results')

@section('content')
@php
    $origin = $criteria['origin'];
    $destination = $criteria['destination'];
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
                <strong>{{ \Carbon\Carbon::parse($criteria['departure_date'])->format('M j') }}@if($criteria['return_date'] ?? null) – {{ \Carbon\Carbon::parse($criteria['return_date'])->format('M j') }}@endif</strong>
            </div>
            <div><small>Travellers</small><strong>{{ $criteria['adults'] }} {{ str('adult')->plural($criteria['adults']) }} · {{ str($criteria['cabin'])->replace('_', ' ')->headline() }}</strong></div>
            <button type="button" class="btn btn-outline-dark" data-bs-toggle="collapse" data-bs-target="#inlineFlightSearch" aria-expanded="false" aria-controls="inlineFlightSearch"><i class="bi bi-pencil"></i> Edit search</button>
        </div>
        <div class="collapse results-inline-search" id="inlineFlightSearch">
            <x-flight-search-form :departure-date="$criteria['departure_date']" :return-date="$criteria['return_date'] ?? $criteria['departure_date']" :criteria="$criteria" :show-results="false" submit-label="Update search" />
        </div>
    </div>
</section>

<section class="flight-results-page public-live-flight-results">
    <div class="container-fluid public-flight-results-container"
         data-public-flight-results-page
         data-search-url="{{ route('flights.search.store') }}"
         data-review-url-template="{{ route('flights.review', ['offer' => '__OFFER__']) }}"
         data-revalidate-url-template="{{ route('flights.offers.revalidate', ['offer' => '__OFFER__']) }}">
        <script type="application/json" data-flight-search-criteria>@json($criteria)</script>

        <div class="public-results-heading">
            <span class="public-eyebrow">Choose your flight</span>
            <h1>{{ $origin }} to {{ $destination }}</h1>
            <p>Searching live airline availability · Prices include taxes and configured Karossy markup</p>
        </div>

        <div class="flight-search-message d-none" role="alert"></div>
        <section class="flight-results d-none" aria-live="polite"></section>
    </div>
</section>

<div class="modal fade public-flight-search-modal" id="publicFlightSearchModal" tabindex="-1" aria-labelledby="publicFlightSearchModalTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center">
                <span class="public-search-progress-icon"><i class="bi bi-airplane"></i></span>
                <span class="modal-eyebrow">Live flight search</span>
                <h2 id="publicFlightSearchModalTitle">Finding your best flights</h2>
                <p data-public-search-status>Checking live fares from {{ $origin }} to {{ $destination }}…</p>
                <div class="public-search-progress" role="progressbar" aria-label="Flight search in progress"><span></span></div>
                <small>Please keep this page open while airlines return their latest availability.</small>
            </div>
        </div>
    </div>
</div>
@endsection

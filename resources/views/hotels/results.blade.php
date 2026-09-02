@extends('layouts.public')

@section('title', 'Hotel results')

@section('content')
<section class="results-search-strip">
    <div class="container public-container">
        <div class="results-search-summary">
            <div><small>Destination</small><strong>{{ $criteria['destination_label'] }}</strong></div>
            <div class="results-summary-divider"></div>
            <div><small>Stay dates</small><strong>{{ \Carbon\Carbon::parse($criteria['check_in'])->format('M j') }} – {{ \Carbon\Carbon::parse($criteria['check_out'])->format('M j') }}</strong></div>
            <div><small>Guests</small><strong>{{ $criteria['adults'] + $criteria['children'] }} travellers · {{ $criteria['rooms'] }} {{ str('room')->plural($criteria['rooms']) }}</strong></div>
            <button type="button" class="btn btn-outline-dark" data-bs-toggle="collapse" data-bs-target="#inlineHotelSearch" aria-expanded="false" aria-controls="inlineHotelSearch"><i class="bi bi-pencil"></i> Edit search</button>
        </div>
        <div class="collapse results-inline-search results-inline-hotel-search" id="inlineHotelSearch">
            <x-hotel-search-form :check-in="$criteria['check_in']" :check-out="$criteria['check_out']" :criteria="$criteria" :action="route('hotels.results')" submit-label="Update search" />
        </div>
    </div>
</section>

<section class="flight-results-page hotel-results-page public-live-hotel-results">
    <div class="container-fluid public-hotel-results-container"
         data-public-hotel-results-page
         data-search-url="{{ route('hotels.search.store') }}">
        <script type="application/json" data-hotel-search-criteria>@json($criteria)</script>

        @if(session('warning'))<div class="alert alert-warning mb-3"><i class="bi bi-clock-history me-2"></i>{{ session('warning') }}</div>@endif

        <div class="results-heading">
            <div>
                <span class="public-eyebrow">Find your stay</span>
                <h1>Hotels in {{ $criteria['destination_label'] }}</h1>
                <p data-hotel-results-summary>Searching live hotel availability · Taxes and fees included</p>
            </div>
            <label class="results-sort d-none" data-hotel-sort>Sort by<select class="form-select"><option value="recommended">Recommended</option><option value="price_asc">Lowest price</option><option value="rating_desc">Highest rating</option></select></label>
        </div>

        <div class="hotel-search-message d-none" role="alert" aria-live="assertive"></div>
        <section class="hotel-results-content d-none" data-hotel-results-content aria-live="polite" aria-busy="true"></section>
    </div>
</section>

<div class="modal fade public-flight-search-modal public-hotel-search-modal" id="publicHotelSearchModal" tabindex="-1" aria-labelledby="publicHotelSearchModalTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center">
                <span class="public-search-progress-icon is-hotel"><i class="bi bi-buildings"></i></span>
                <span class="modal-eyebrow">Live hotel search</span>
                <h2 id="publicHotelSearchModalTitle">Finding the right stays</h2>
                <p data-public-hotel-search-status>Checking rooms and live rates in {{ $criteria['destination_label'] }}…</p>
                <div class="public-search-progress" role="progressbar" aria-label="Hotel search in progress"><span></span></div>
                <small>We are comparing available rooms, cancellation terms and the latest prices.</small>
            </div>
        </div>
    </div>
</div>
@endsection

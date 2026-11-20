@extends('layouts.crm')

@section('title', 'Hotel desk')
@section('topbar-title', 'Hotel desk')

@section('content')
<div class="crm-page-heading"><div><p class="crm-eyebrow">Operations · Live supplier search</p><h1>Hotel desk</h1><p>Search live availability and rates for the customer before creating the stay booking.</p></div><a class="crm-button crm-button-quiet" href="{{ route('crm.bookings.create') }}"><i class="bi bi-arrow-left"></i> Booking launcher</a></div>

<section class="crm-card crm-travel-search-card">
    <x-hotel-search-form :check-in="$defaultCheckIn" :check-out="$defaultCheckOut" :action="route('crm.travel.hotels.results')" :criteria="$criteria ?? []" submit-label="Search hotels" />
</section>

@if(is_array($offers))
    @php($money = fn (int $minor, string $code) => \App\Support\CurrencyMetadata::format($minor, $code))
    <section class="crm-travel-results"><div class="crm-travel-results-heading"><div><p class="crm-eyebrow">Live availability</p><h2>{{ count($offers) }} {{ str('property')->plural(count($offers)) }} found</h2></div><span>{{ $currency }} customer pricing applied</span></div>
        <div class="crm-hotel-result-list">
        @forelse($offers as $offer)
            <article class="crm-card crm-hotel-result"><div class="crm-hotel-result-icon"><i class="bi bi-building"></i></div><div class="crm-hotel-result-copy"><div><h3>{{ $offer['name'] }}</h3>@if($offer['rating'])<span>{{ number_format($offer['rating'], 1) }} <i class="bi bi-star-fill"></i></span>@endif</div><p><i class="bi bi-geo-alt"></i> {{ data_get($offer, 'location.address') }}, {{ data_get($offer, 'location.city') }}</p><small>{{ $offer['room_name'] }} · {{ $offer['rate_name'] }} @if($offer['breakfast_included'])· Breakfast included @endif</small></div><div class="crm-hotel-result-price"><small>Customer total</small><strong>{{ $money($offer['price']['total_minor'], $offer['price']['currency']) }}</strong><span>{{ $money($offer['price']['nightly_minor'], $offer['price']['currency']) }} / night</span><span class="crm-pending-action">Booking step next</span></div></article>
        @empty
            <div class="crm-card crm-empty-state"><i class="bi bi-building-x"></i><strong>No hotel offers returned</strong><span>Try different dates or a nearby destination.</span></div>
        @endforelse
        </div>
    </section>
@endif

<section class="crm-card crm-travel-note"><i class="bi bi-person-check"></i><div><strong>Ownership is applied at booking creation</strong><p>When a selected stay becomes a booking, the CRM will assign it to a customer agent and add it to their activity timeline automatically.</p></div></section>
@endsection

@extends('layouts.crm')

@section('title', 'Flight desk')
@section('topbar-title', 'Flight desk')

@section('content')
<div class="crm-page-heading"><div><p class="crm-eyebrow">Operations · Live supplier search</p><h1>Flight desk</h1><p>Search live airline availability and prepare a customer booking without leaving the CRM.</p></div><a class="crm-button crm-button-quiet" href="{{ route('crm.bookings.create') }}"><i class="bi bi-arrow-left"></i> Booking launcher</a></div>

<section class="crm-card crm-travel-search-card" data-crm-flight-search-page data-search-url="{{ route('crm.travel.flights.search') }}" data-revalidate-url-template="{{ route('crm.travel.flights.revalidate', ['offer' => '__OFFER__']) }}">
    <x-flight-search-form :departure-date="$defaultDepartureDate" :return-date="$defaultReturnDate" />
</section>

<section class="crm-card crm-travel-note"><i class="bi bi-shield-check"></i><div><strong>Supplier safeguards stay in place</strong><p>Every fare is priced through the existing supplier workflow. Selecting a fare remains subject to live revalidation before any booking record or supplier confirmation is created.</p></div></section>
@endsection

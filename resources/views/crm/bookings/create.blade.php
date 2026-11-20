@extends('layouts.crm')

@section('title', 'Create booking')
@section('topbar-title', 'New booking')

@section('content')
<div class="crm-page-heading"><div><p class="crm-eyebrow">Operations</p><h1>Start a booking</h1><p>Choose the travel service, then complete the supplier search and customer details inside CRM.</p></div><a class="crm-button crm-button-quiet" href="{{ route('crm.bookings.index') }}"><i class="bi bi-x-lg"></i> Cancel</a></div>
<section class="crm-booking-launcher">
    <article class="crm-card crm-booking-launch-card">
        <span class="crm-booking-launch-icon"><i class="bi bi-airplane"></i></span>
        <div><h2>Flight booking</h2><p>Search live fares, select an itinerary, capture travellers, quote the customer, then create the booking.</p><a class="crm-button" href="{{ route('crm.travel.flights.index') }}"><i class="bi bi-airplane"></i> Open flight desk</a></div>
    </article>
    <article class="crm-card crm-booking-launch-card">
        <span class="crm-booking-launch-icon hotel"><i class="bi bi-buildings"></i></span>
        <div><h2>Hotel booking</h2><p>Search live availability, confirm rooms and guest details, then create and route the booking to a customer agent.</p><a class="crm-button" href="{{ route('crm.travel.hotels.index') }}"><i class="bi bi-buildings"></i> Open hotel desk</a></div>
    </article>
</section>
<section class="crm-card crm-booking-routing-note"><i class="bi bi-person-check"></i><div><strong>Automatic customer ownership</strong><p>Once a flight or hotel booking is created, Karossy assigns it to the active customer agent with the lightest open-booking workload. A manager can reassign it at any time.</p></div></section>
@endsection

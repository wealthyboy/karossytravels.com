@extends('layouts.public')
@section('title', 'Hotel reservation complete')
@section('content')
@php($stay = data_get($booking->details, 'stay', []))
<section class="booking-page checkout-complete-page"><div class="container public-container">
    @include('checkout._progress', ['step' => 3])
    <div class="confirmation-hero"><span class="completion-icon"><i class="bi bi-check-lg"></i></span><div><span class="public-eyebrow">Reservation received</span><h1>Your hotel booking is complete</h1><p>Your confirmation and receipt have been sent to <strong>{{ data_get($order->customer, 'email') }}</strong>.</p></div><span class="confirmation-status"><i class="bi bi-check-circle-fill"></i> Paid</span></div>
    <div class="row g-4"><div class="col-lg-8"><article class="confirmation-panel"><div class="confirmation-section-title"><span><i class="bi bi-building-check"></i></span><div><h2>{{ data_get($stay, 'hotel_name') }}</h2><p>{{ data_get($stay, 'room_name') }} · {{ data_get($stay, 'rate_name') }}</p></div></div><div class="confirmation-price-lines"><div><span>Check-in</span><strong>{{ data_get($stay, 'check_in') }}</strong></div><div><span>Check-out</span><strong>{{ data_get($stay, 'check_out') }}</strong></div><div><span>Guests</span><strong>{{ data_get($stay, 'adults') }} adults · {{ data_get($stay, 'rooms') }} room(s)</strong></div></div></article></div>
    <aside class="col-lg-4"><div class="confirmation-panel confirmation-summary"><span class="public-eyebrow">Booking reference</span><h2>{{ $order->reference }}</h2><strong class="confirmation-total">{{ \App\Support\CurrencyMetadata::format($order->total_minor, $order->currency) }}</strong><div class="confirmation-contact"><i class="bi bi-envelope-check"></i><span><small>Receipt sent to</small><strong>{{ data_get($order->customer, 'email') }}</strong></span></div><a class="btn btn-karossy w-100" href="{{ route('account.bookings.show', $booking) }}">View booking</a><a class="btn btn-outline-dark w-100" href="{{ route('home') }}">Back to home</a></div></aside></div>
</div></section>
@endsection

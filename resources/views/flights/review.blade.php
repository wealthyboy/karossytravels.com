@extends('layouts.public')

@section('title', 'Review your flight')

@section('content')
@php
    $segments = $offer->itinerary;
    $fare = $offer->fare_summary;
    $money = fn (int $minor) => \App\Support\CurrencyMetadata::format($minor, $currency);
    $origin = $offer->flightSearch->origin;
    $destination = $offer->flightSearch->destination;
@endphp
<section class="booking-page"><div class="container public-container">
    <a class="booking-back" href="{{ route('home') }}#travel-search"><i class="bi bi-arrow-left"></i> Back to search</a>
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <div class="booking-heading"><span class="public-eyebrow">Review your trip</span><h1>{{ $origin }} to {{ $destination }}</h1><p>Check the live itinerary and fare before entering traveller details.</p></div>
    <div class="row g-4"><div class="col-lg-8">
        <div class="booking-card"><div class="booking-card-title"><div><span class="airline-token">{{ $fare['validating_airline'] ?: 'AIR' }}</span><div><h2>{{ $fare['validating_airline'] ?: 'Partner airline' }}</h2><p>{{ count($segments) > 1 ? 'Multi-segment' : 'One-way' }} · 1 traveller</p></div></div><span class="status-chip"><i class="bi bi-clock"></i> Live fare</span></div>
            @foreach($segments as $segment)
            <div class="review-leg"><div class="review-leg-label"><strong>Flight {{ $loop->iteration }}</strong><small>{{ \Carbon\Carbon::parse($segment['departure_at'])->format('M j, Y') }}</small></div><div class="review-time"><strong>{{ \Carbon\Carbon::parse($segment['departure_at'])->format('H:i') }}</strong><span>{{ $segment['origin'] }}</span></div><div class="review-route"><i class="bi bi-airplane-fill"></i><span></span><small>{{ $segment['flight_number'] ?? '' }}</small></div><div class="review-time text-end"><strong>{{ \Carbon\Carbon::parse($segment['arrival_at'])->format('H:i') }}</strong><span>{{ $segment['destination'] }}</span></div></div>
            @endforeach
        </div>
        <div class="booking-card mt-4"><h2 class="section-title mt-0">Fare conditions</h2><ul class="mb-0"><li>Taxes and applicable fees are included.</li><li>{{ ($fare['refundable'] ?? false) ? 'Refundable fare' : 'Cancellation and change fees may apply' }}.</li><li>The fare will be revalidated before booking.</li></ul></div>
    </div><aside class="col-lg-4"><div class="checkout-summary sticky-lg-top"><h2>Price summary</h2><div><span>Flight, taxes and fees</span><strong>{{ $money($total['amount_minor']) }}</strong></div><div class="summary-total"><span>Total</span><strong>{{ $money($total['amount_minor']) }}</strong></div><small>Displayed in {{ $currency }}. Final availability is confirmed before booking.</small><button class="btn btn-karossy w-100" type="button" data-bs-toggle="modal" data-bs-target="#publicFlightRevalidationModal" data-public-revalidate data-url="{{ route('flights.offers.revalidate', $offer) }}">Continue to travellers <i class="bi bi-arrow-right"></i></button><p><i class="bi bi-shield-check"></i> Secure booking with Karossy support</p></div></aside></div>
</div></section>

<div class="modal fade flight-revalidation-modal" id="publicFlightRevalidationModal" tabindex="-1" aria-labelledby="publicFlightRevalidationTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center"><div data-public-revalidation-progress><span class="revalidation-icon"><i class="bi bi-shield-check"></i></span><h2 id="publicFlightRevalidationTitle">Confirming your flight</h2><p>Checking availability and the latest fare directly with the airline…</p><div class="revalidation-progress" aria-hidden="true"><span></span></div><small>Please keep this window open for a few seconds.</small></div><div class="d-none" data-public-revalidation-error><span class="revalidation-icon is-error"><i class="bi bi-exclamation-lg"></i></span><h2>We could not confirm this fare</h2><p data-public-revalidation-message></p><button class="btn btn-karossy w-100" type="button" data-bs-dismiss="modal">Choose another fare</button></div></div></div></div></div>
@endsection

@push('scripts')
<script>
document.querySelector('[data-public-revalidate]')?.addEventListener('click', async event => {
    const button = event.currentTarget;
    const progress = document.querySelector('[data-public-revalidation-progress]');
    const error = document.querySelector('[data-public-revalidation-error]');
    progress.classList.remove('d-none');
    error.classList.add('d-none');
    try {
        const response = await fetch(button.dataset.url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });
        const body = await response.json();
        if (!response.ok) throw new Error(body.message || 'The airline could not confirm this fare.');
        window.location.href = body.data.continue_url;
    } catch (exception) {
        progress.classList.add('d-none');
        error.querySelector('[data-public-revalidation-message]').textContent = exception.message;
        error.classList.remove('d-none');
    }
});
</script>
@endpush

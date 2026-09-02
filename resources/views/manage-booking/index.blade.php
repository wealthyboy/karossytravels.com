@extends('layouts.public')

@section('title', 'Manage booking')

@section('content')
<section class="manage-booking-page">
    <div class="container public-container">
        <div class="manage-booking-shell">
            <div class="manage-booking-copy">
                <span class="public-eyebrow"><i class="bi bi-suitcase-lg"></i> Your trip</span>
                <h1>Manage your booking</h1>
                <p>Enter the Karossy booking reference and the email used during checkout to securely view your trip.</p>
                <div class="manage-booking-benefits"><span><i class="bi bi-check-circle"></i> View booking status</span><span><i class="bi bi-check-circle"></i> Review traveller and itinerary details</span><span><i class="bi bi-check-circle"></i> Find your booking locator</span></div>
            </div>
            <form class="manage-booking-form" action="{{ route('manage-booking.lookup') }}" method="POST" novalidate data-manage-booking-form>
                @csrf
                <h2>Find your booking</h2>
                <p>Your reference is shown on your confirmation email and receipt.</p>
                @if($errors->any())<div class="alert alert-danger" data-manage-booking-error>{{ $errors->first() }}</div>@else<div class="alert alert-danger d-none" data-manage-booking-error></div>@endif
                <label class="form-label" for="booking-reference">Booking reference</label>
                <input class="form-control text-uppercase @error('reference') is-invalid @enderror" id="booking-reference" name="reference" value="{{ old('reference') }}" autocomplete="off" placeholder="e.g. KAR-ABC123">
                <label class="form-label mt-3" for="booking-email">Email address</label>
                <input class="form-control @error('email') is-invalid @enderror" id="booking-email" name="email" value="{{ old('email') }}" type="email" autocomplete="email" placeholder="name@example.com">
                <button class="btn btn-karossy w-100 mt-4" type="submit"><span>Find my booking</span><i class="bi bi-arrow-right"></i></button>
                @auth<a class="manage-booking-account-link" href="{{ route('account.bookings.index') }}">Or view every booking in your account</a>@endauth
            </form>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-manage-booking-form]');
    if (!form) return;
    const error = form.querySelector('[data-manage-booking-error]');
    const clear = event => {
        event.target.classList.remove('is-invalid');
        error.classList.add('d-none');
    };
    form.addEventListener('input', clear);
    form.addEventListener('submit', event => {
        const reference = form.elements.reference.value.trim();
        const email = form.elements.email.value.trim();
        let message = '';
        if (reference.length < 4) message = 'Enter the booking reference from your confirmation email.';
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) message = 'Enter the email address used for the booking.';
        if (!message) return;
        event.preventDefault();
        error.textContent = message;
        error.classList.remove('d-none');
        const field = reference.length < 4 ? form.elements.reference : form.elements.email;
        field.classList.add('is-invalid');
        field.focus();
    });
})();
</script>
@endpush

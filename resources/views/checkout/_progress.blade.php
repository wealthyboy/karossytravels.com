@php($complete = ($step ?? 1) >= 3)
<div class="checkout-single-step {{ $complete ? 'is-complete' : '' }}" aria-label="{{ $complete ? 'Booking complete' : 'Secure checkout' }}">
    <span class="checkout-single-step-icon"><i class="bi {{ $complete ? 'bi-check-lg' : 'bi-shield-lock' }}"></i></span>
    <div class="checkout-single-step-copy">
        <small>{{ $complete ? 'Completed securely' : 'One simple checkout' }}</small>
        <strong>{{ $complete ? 'Booking complete' : 'Secure checkout' }}</strong>
        <span>{{ $complete ? 'Your reservation and payment details are ready.' : 'Add traveller details, review your trip and pay securely.' }}</span>
    </div>
    <span class="checkout-single-step-status"><i class="bi {{ $complete ? 'bi-check-circle-fill' : 'bi-lock-fill' }}"></i> {{ $complete ? 'Confirmed' : 'Protected' }}</span>
</div>

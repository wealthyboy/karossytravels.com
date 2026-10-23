@if(($step ?? 1) >= 3)
<div class="checkout-single-step is-complete" aria-label="Booking complete">
    <span class="checkout-single-step-icon"><i class="bi bi-check-lg"></i></span>
    <div class="checkout-single-step-copy">
        <small>Completed securely</small>
        <strong>Booking complete</strong>
        <span>Your reservation and payment details are ready.</span>
    </div>
    <span class="checkout-single-step-status"><i class="bi bi-check-circle-fill"></i> Confirmed</span>
</div>
@endif

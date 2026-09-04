@extends('layouts.public')
@section('title', 'Reserve '.$offer->name)
@section('content')
@php
    $money = fn (int $minor) => \App\Support\CurrencyMetadata::format($minor, $currency);
    $search = $offer->search;
@endphp
<section class="booking-page"><div class="container public-container">
    @include('checkout._progress', ['step' => 1])
    <div class="booking-heading"><span class="public-eyebrow">Secure hotel booking</span><h1>Reserve your room</h1><p>Review the stay, enter the lead guest details and complete payment securely with Paystack.</p></div>
    @if($recoverableAttempt)<div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-3" role="alert"><span><strong>Payment confirmed.</strong> Do not pay again. Reference: {{ $recoverableAttempt->reference }}</span>@if(!$recoverableAttempt->reservation_attempted_at)<button class="btn btn-sm btn-outline-dark" type="button" data-retry-hotel-confirmation>Retry hotel confirmation</button>@else<span class="fw-bold">Confirmation is under review</span>@endif</div>@endif
    <div class="alert alert-danger d-none" data-hotel-error></div>
    <form class="row g-4" method="POST" action="{{ route('hotels.checkout.payment', $offer) }}" data-verify-url="{{ route('hotels.checkout.verify', $offer) }}" @if($recoverableAttempt) data-recovery-reference="{{ $recoverableAttempt->reference }}" @endif data-hotel-checkout>
        @csrf
        <div class="col-lg-8">
            <div class="booking-card">
                <div class="booking-card-title"><div><span class="traveller-number"><i class="bi bi-person"></i></span><div><h2>Lead guest</h2><p>The reservation and receipt will be issued to this guest.</p></div></div></div>
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label">First name</label><input class="form-control" name="first_name" required autocomplete="given-name" value="{{ old('first_name', $customer?->first_name ?: auth()->user()?->first_name) }}"></div>
                    <div class="col-md-6"><label class="form-label">Last name</label><input class="form-control" name="last_name" required autocomplete="family-name" value="{{ old('last_name', $customer?->last_name ?: auth()->user()?->last_name) }}"></div>
                    <div class="col-md-6"><label class="form-label">Email address</label><input class="form-control" name="email" type="email" required autocomplete="email" value="{{ old('email', $customer?->email ?: auth()->user()?->email) }}"></div>
                    <div class="col-md-6"><label class="form-label">Mobile number</label><x-phone-input :value="$customer?->phone" /></div>
                    <div class="col-12"><label class="form-label">Special requests <span class="text-muted">(optional)</span></label><textarea class="form-control" name="special_requests" rows="3" placeholder="Bed preference, arrival information or accessibility request"></textarea></div>
                </div>
            </div>
            <label class="form-check checkout-terms mt-4"><input class="form-check-input" name="terms" value="1" type="checkbox" required><span class="form-check-label">I accept the room conditions, cancellation policy and Karossy booking terms.</span></label>
            <div class="checkout-actions"><a class="btn btn-outline-dark" href="{{ route('hotels.rooms', $offer) }}"><i class="bi bi-arrow-left"></i> Back to rooms</a><button class="btn btn-karossy checkout-pay-button" type="submit" data-hotel-pay @disabled($recoverableAttempt)>@if($recoverableAttempt)Payment received@else Pay {{ $money($total['amount_minor']) }} <i class="bi bi-arrow-right"></i>@endif</button></div>
        </div>
        <aside class="col-lg-4"><div class="checkout-summary sticky-lg-top">
            <div class="mini-itinerary"><span class="airline-token"><i class="bi bi-building"></i></span><div><strong>{{ $offer->name }}</strong><small>{{ $offer->room_name }}</small></div></div>
            <div><span>Check-in</span><strong>{{ $search->check_in->format('D, M j, Y') }}</strong></div><div><span>Check-out</span><strong>{{ $search->check_out->format('D, M j, Y') }}</strong></div>
            <div><span>{{ $search->rooms }} {{ str('room')->plural($search->rooms) }} · {{ $search->adults }} {{ str('guest')->plural($search->adults) }}</span><strong>{{ $search->check_in->diffInDays($search->check_out) }} nights</strong></div>
            <div class="summary-total"><span>Total</span><strong>{{ $money($total['amount_minor']) }}</strong></div><small>Displayed in {{ $currency }}. Includes the configured hotel markup, taxes and fees supplied with this offer.</small><p><i class="bi bi-shield-check"></i> Paystack secure checkout</p>
        </div></aside>
    </form>
</div></section>
<div class="visa-finishing-overlay d-none" data-hotel-finishing><div><span><i class="bi bi-building-check"></i></span><h2>Completing your reservation</h2><p>Payment received. We are creating your booking and receipt.</p><div class="revalidation-progress"><span></span></div><small>Please keep this page open.</small></div></div>
@endsection
@push('scripts')
@unless(app()->environment(['local','testing']) && config('travel.checkout.demo_payment_enabled'))<script src="https://js.paystack.co/v2/inline.js"></script>@endunless
<script>
(()=>{const form=document.querySelector('[data-hotel-checkout]');if(!form)return;const button=form.querySelector('[data-hotel-pay]'),retryButton=document.querySelector('[data-retry-hotel-confirmation]'),errorBox=document.querySelector('[data-hotel-error]'),overlay=document.querySelector('[data-hotel-finishing]'),csrf=document.querySelector('meta[name="csrf-token"]').content;const fail=m=>{overlay.classList.add('d-none');button.disabled=!!form.dataset.recoveryReference;if(retryButton)retryButton.disabled=false;errorBox.textContent=m;errorBox.classList.remove('d-none');errorBox.scrollIntoView({behavior:'smooth',block:'center'})};const post=async(url,payload)=>{const response=await fetch(url,{method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf,...(payload instanceof FormData?{}:{'Content-Type':'application/json'})},body:payload instanceof FormData?payload:JSON.stringify(payload)});const body=await response.json();if(!response.ok)throw new Error(Object.values(body.errors||{}).flat()[0]||body.message||'The request could not be completed.');return body};const finish=body=>{overlay.classList.remove('d-none');window.location.assign(body.redirect)};form.addEventListener('submit',async e=>{e.preventDefault();if(form.dataset.recoveryReference)return;if(!form.reportValidity())return;button.disabled=true;errorBox.classList.add('d-none');overlay.classList.remove('d-none');try{const init=await post(form.action,new FormData(form));if(init.redirect){finish(init);return}if(typeof window.PaystackPop!=='function')throw new Error('The secure payment window did not load. Check your connection and retry.');overlay.classList.add('d-none');new window.PaystackPop().newTransaction({key:init.public_key,email:init.email,amount:init.amount_minor,currency:init.currency,reference:init.reference,firstName:init.first_name,lastName:init.last_name,phone:init.phone,metadata:init.metadata,onSuccess:async tx=>{overlay.classList.remove('d-none');try{finish(await post(form.dataset.verifyUrl,{reference:tx.reference||init.reference,transaction_id:tx.transaction||tx.trans||null}))}catch(error){fail(error.message)}},onCancel:()=>fail('Payment was not completed. You can try again.'),onError:error=>fail(error?.message||'Payment could not be opened.')})}catch(error){fail(error.message)}});retryButton?.addEventListener('click',async()=>{retryButton.disabled=true;errorBox.classList.add('d-none');overlay.classList.remove('d-none');try{finish(await post(form.dataset.verifyUrl,{reference:form.dataset.recoveryReference}))}catch(error){fail(error.message)}})})();
</script>
@endpush

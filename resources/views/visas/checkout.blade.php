@extends('layouts.public')
@section('title', 'Visa application checkout')
@section('content')
@php($symbol = $currency === 'NGN' ? '₦' : '$')
<section class="visa-checkout-page" data-visa-checkout-page data-demo-payment="{{ $demoPaymentEnabled ? 'true' : 'false' }}">
    <div class="container public-container">
        <a class="service-back" href="{{ route('visas.show', ['visa' => $visa, 'travellers' => $travellers, 'consultation' => $consultation ? 1 : null]) }}"><i class="bi bi-arrow-left"></i> Review requirements</a>
        <header class="service-page-heading"><span class="public-eyebrow">Secure visa checkout</span><h1>Tell us about the {{ Str::plural('traveller', $travellers) }}</h1><p>Enter each name exactly as it appears on the passport. Your application is created only after payment is verified.</p></header>
        <div class="alert alert-danger d-none" data-visa-error role="alert"></div>
        <form class="visa-checkout-grid" data-visa-checkout-form action="{{ route('visas.payment.initialize', $visa) }}" data-verify-template="{{ route('visas.payment.verify', '__APPLICATION__') }}" novalidate>
            @csrf<input type="hidden" name="travellers" value="{{ $travellers }}"><input type="hidden" name="consultation" value="{{ $consultation ? 1 : 0 }}">
            <main>
                @foreach(range(1, $travellers) as $number)
                <article class="visa-checkout-card">
                    <div class="visa-checkout-card-title"><span>{{ $number }}</span><div><h2>Traveller {{ $number }}</h2><p>Passport and identity details</p></div></div>
                    <div class="visa-form-grid">
                        <label><span>First name</span><input name="applicants[{{ $number - 1 }}][first_name]" autocomplete="given-name" required></label>
                        <label><span>Last name</span><input name="applicants[{{ $number - 1 }}][last_name]" autocomplete="family-name" required></label>
                        <label><span>Date of birth</span><input type="date" name="applicants[{{ $number - 1 }}][date_of_birth]" max="{{ now()->subDay()->toDateString() }}" required></label>
                        <label><span>Passport number</span><input name="applicants[{{ $number - 1 }}][passport_number]" autocomplete="off" required></label>
                        <label><span>Passport expiry</span><input type="date" name="applicants[{{ $number - 1 }}][passport_expiry]" min="{{ now()->addDay()->toDateString() }}" required></label>
                    </div>
                </article>
                @endforeach
                <article class="visa-checkout-card">
                    <div class="visa-checkout-card-title"><span><i class="bi bi-envelope"></i></span><div><h2>Contact details</h2><p>Application updates and your receipt will be sent here.</p></div></div>
                    <div class="visa-form-grid">
                        <label class="span-2"><span>Full name</span><input name="contact[name]" value="{{ auth()->user()?->name }}" autocomplete="name" required></label>
                        <label><span>Email address</span><input type="email" name="contact[email]" value="{{ auth()->user()?->email }}" autocomplete="email" required></label>
                        <label><span>Phone number</span><input type="tel" name="contact[phone]" autocomplete="tel" required></label>
                    </div>
                    <label class="visa-terms"><input class="form-check-input" type="checkbox" name="terms" value="1" required><span>I confirm these details are correct and accept Karossy's visa service and refund terms.</span></label>
                </article>
            </main>
            <aside class="visa-order-card visa-checkout-summary">
                <span class="visa-badge">{{ ucfirst($visa->visa_type) }} visa</span><h2>{{ $visa->name }}</h2><p>{{ $visa->passport_country }} passport to {{ $visa->country }}</p>
                <div><span>Visa service × {{ $travellers }}</span><strong>{{ $symbol }}{{ number_format($visaTotal['amount_minor']/100, 2) }}</strong></div>
                @if($consultation)<div><span>Visa consultation</span><strong>{{ $symbol }}{{ number_format($consultationTotal['amount_minor']/100, 2) }}</strong></div>@endif
                <div class="visa-order-total"><span>Total</span><strong>{{ $symbol }}{{ number_format($grandTotal['amount_minor']/100, 2) }}</strong></div>
                <button class="btn btn-karossy" type="submit" data-visa-pay>Pay {{ $symbol }}{{ number_format($grandTotal['amount_minor']/100, 2) }} <i class="bi bi-arrow-right"></i></button>
                <small><i class="bi bi-shield-lock"></i> Payment is securely processed. Card details never touch Karossy servers.</small>
            </aside>
        </form>
    </div>
</section>
<div class="visa-finishing-overlay d-none" data-visa-finishing role="dialog" aria-modal="true" aria-labelledby="visaFinishingTitle"><div><span><i class="bi bi-passport"></i></span><h2 id="visaFinishingTitle" data-visa-progress-title>Finishing things up</h2><p data-visa-progress-copy>Payment received. We are saving your application and preparing the next steps.</p><div class="revalidation-progress"><span></span></div><small>Please keep this page open.</small></div></div>
@endsection
@push('scripts')
@unless($demoPaymentEnabled)<script src="https://js.paystack.co/v2/inline.js"></script>@endunless
<script>
(() => {
 const form=document.querySelector('[data-visa-checkout-form]'); if(!form)return;
 const errorBox=document.querySelector('[data-visa-error]'),overlay=document.querySelector('[data-visa-finishing]'),button=form.querySelector('[data-visa-pay]');
 const csrf=document.querySelector('meta[name="csrf-token"]').content;
 const fail=(message)=>{overlay.classList.add('d-none');button.disabled=false;errorBox.textContent=message;errorBox.classList.remove('d-none');errorBox.scrollIntoView({behavior:'smooth',block:'center'});};
 form.querySelectorAll('input').forEach(input=>input.addEventListener('input',()=>{input.closest('label')?.classList.remove('is-invalid');errorBox.classList.add('d-none');}));
 const post=async(url,payload)=>{const response=await fetch(url,{method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf,...(payload instanceof FormData?{}:{'Content-Type':'application/json'})},body:payload instanceof FormData?payload:JSON.stringify(payload)});const body=await response.json();if(!response.ok)throw new Error(Object.values(body.errors||{}).flat()[0]||body.message||'The request could not be completed.');return body;};
 const finish=body=>{overlay.classList.remove('d-none');document.querySelector('[data-visa-progress-copy]').textContent='Payment received. We are saving your application and preparing your confirmation now.';window.location.assign(body.redirect);};
 form.addEventListener('submit',async event=>{event.preventDefault();let firstInvalid=null;form.querySelectorAll('[required]').forEach(input=>{const invalid=input.type==='checkbox'?!input.checked:!input.value.trim()||!input.checkValidity();input.closest('label')?.classList.toggle('is-invalid',invalid);if(invalid&&!firstInvalid)firstInvalid=input;});if(firstInvalid){fail('Please complete the highlighted fields correctly.');firstInvalid.focus();return;}errorBox.classList.add('d-none');button.disabled=true;overlay.classList.remove('d-none');try{const init=await post(form.action,new FormData(form));if(init.redirect){finish(init);return;}if(typeof window.PaystackPop!=='function')throw new Error('The secure payment window did not load. Please check your connection and retry.');overlay.classList.add('d-none');const popup=new window.PaystackPop();popup.newTransaction({key:init.public_key,email:init.email,amount:init.amount_minor,currency:init.currency,reference:init.reference,firstName:init.first_name,lastName:init.last_name,phone:init.phone,metadata:init.metadata,onSuccess:async transaction=>{overlay.classList.remove('d-none');try{const url=form.dataset.verifyTemplate.replace('__APPLICATION__',init.metadata.visa_application_id);finish(await post(url,{reference:transaction.reference||init.reference,transaction_id:transaction.trans||null}));}catch(error){fail(error.message);}},onCancel:()=>fail('Payment was not completed. You can try again when you are ready.'),onError:error=>fail(error?.message||'Payment could not be opened.')});}catch(error){fail(error.message);}});
})();
</script>
@endpush

@extends('layouts.public')

@section('title', 'Review and confirm booking')

@section('content')
@php
    $symbol = $currency === 'NGN' ? '₦' : ($currency === 'USD' ? '$' : $currency.' ');
    $money = fn (int $minor) => $symbol.number_format($minor / 100, 2);
    $travellers = $checkout['travellers'];
@endphp
<section class="booking-page" data-flight-checkout-page data-demo-payment="{{ $demoPaymentEnabled ? 'true' : 'false' }}"><div class="container public-container">
    @include('checkout._progress', ['step' => 2])
    <div class="booking-heading"><span class="public-eyebrow">Secure checkout</span><h1>Review and pay for your flight</h1><p>We check the live fare again behind the scenes before confirming your booking.</p></div>

    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <div class="alert alert-danger d-none" data-booking-error role="alert"></div>

    <form id="public-booking-confirmation-form" action="{{ route('checkout.payment.initialize', $offer) }}" data-verify-url="{{ route('checkout.payment.verify', $offer) }}" method="POST" class="row g-4" novalidate>
        @csrf
        <div class="col-lg-8">
            <div class="booking-card">
                <div class="payment-security"><span><i class="bi bi-shield-check"></i></span><div><strong>Live fare protection</strong><small>Your itinerary and price will be checked again immediately before the PNR is created.</small></div><i class="bi bi-lock-fill"></i></div>
                <div class="booking-review-list">
                    @foreach($travellers as $index => $traveller)
                        <div class="booking-review-person"><span class="traveller-number">{{ $index + 1 }}</span><div><strong>{{ $traveller['title'] }} {{ $traveller['first_name'] }} {{ $traveller['last_name'] }}</strong><small>{{ $traveller['type'] === 'ADT' ? 'Adult' : ($traveller['type'] === 'CNN' ? 'Child' : 'Infant') }} · Passport ending {{ str($traveller['passport_number'])->take(-4) }}</small></div><i class="bi bi-check-circle-fill"></i></div>
                    @endforeach
                </div>
            </div>

            @if($addons->isNotEmpty())
            <div class="booking-card mt-4">
                <div class="booking-card-title"><div><span class="booking-card-icon"><i class="bi bi-bag-plus"></i></span><div><h2>Make the trip easier</h2><p>Optional services are added to this booking only when selected.</p></div></div></div>
                <div class="checkout-addon-grid">
                @foreach($addons as $addon)
                    @php
                        $displayPrice = data_get($addon, 'display_price.amount_minor', $addon->price_cents);
                    @endphp
                    <label class="checkout-addon">
                        <input class="form-check-input" type="checkbox" name="addons[]" value="{{ $addon->id }}" data-addon-price="{{ $displayPrice }}">
                        @if($addon->image_path)<img src="{{ Storage::url($addon->image_path) }}" alt="">@else<span class="checkout-addon-icon"><i class="bi bi-stars"></i></span>@endif
                        <span class="checkout-addon-copy"><strong>{{ $addon->title }}</strong><small>{{ $addon->description }}</small></span>
                        <b>+{{ $money($displayPrice) }}</b>
                    </label>
                @endforeach
                </div>
            </div>
            @endif

            <div class="booking-card mt-4">
                <div class="booking-card-title"><div><span class="booking-card-icon"><i class="bi bi-file-earmark-check"></i></span><div><h2>Rules that apply to this booking</h2><p>{{ $airlineCode ?: 'Airline' }} conditions and Karossy service rules.</p></div></div></div>
                @forelse($fareRules as $rule)
                    <details class="checkout-rule" @if($loop->first) open @endif><summary><span>{{ $rule->is_karossey_rule ? 'Karossy' : $rule->airline_code }}</span><strong>{{ $rule->title }}</strong><i class="bi bi-chevron-down"></i></summary><div>{!! nl2br(e($rule->content)) !!}</div></details>
                @empty
                    <div class="checkout-rule-empty"><i class="bi bi-info-circle"></i><span><strong>Airline fare conditions apply</strong>The detailed fare rules will be reconfirmed during ticketing. Karossy’s standard terms still apply.</span></div>
                @endforelse
            </div>

            <div class="booking-card mt-4">
                <h2 class="section-title mt-0">Contact and booking conditions</h2>
                <p class="checkout-contact"><i class="bi bi-envelope"></i> Confirmation will be sent to <strong>{{ data_get($checkout, 'contact.email') }}</strong></p>
                <label class="form-check"><input class="form-check-input" name="terms" value="1" type="checkbox"><span class="form-check-label">I confirm the traveller information is correct and accept the airline rules shown above, Karossy rules, cancellation policy and terms of service.</span></label>
                <div class="checkout-reservation-note"><i class="bi bi-shield-lock"></i><span><strong>{{ $demoPaymentEnabled ? 'Booking confirmation' : 'Secure online payment' }}</strong>{{ $demoPaymentEnabled ? 'The final live price will be checked before this development booking is completed.' : 'Karossy never receives or stores your card details. Payment is accepted only in NGN or USD and verified before a PNR is created.' }}</span></div>
            </div>

            <div class="checkout-actions"><a href="{{ route('checkout.travellers', $offer) }}" class="btn btn-outline-dark"><i class="bi bi-arrow-left"></i> Edit travellers</a><button class="btn btn-karossy checkout-pay-button" type="button" data-open-booking-payment data-base-total="{{ $total['amount_minor'] }}" data-currency-symbol="{{ $symbol }}">Pay <span data-confirm-total>{{ $money($total['amount_minor']) }}</span> <i class="bi bi-arrow-right"></i></button></div>
        </div>
        <aside class="col-lg-4">@include('checkout._summary')</aside>
    </form>
</div></section>

<div class="modal fade flight-revalidation-modal" id="publicBookingConfirmationModal" tabindex="-1" aria-labelledby="publicBookingProgressTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center"><div data-booking-progress><span class="revalidation-icon"><i class="bi bi-shield-check"></i></span><h2 id="publicBookingProgressTitle" data-payment-progress-title>Checking the live fare</h2><p data-payment-progress-copy>Validating the latest price and availability. Please do not close this page.</p><div class="revalidation-progress" aria-hidden="true"><span></span></div><small data-payment-progress-note>We will continue only if the itinerary and total are still valid.</small></div></div></div></div></div>
@endsection

@push('scripts')
@unless($demoPaymentEnabled)<script src="https://js.paystack.co/v2/inline.js"></script>@endunless
<script>
(() => {
    const form = document.querySelector('#public-booking-confirmation-form');
    const demoPayment = document.querySelector('[data-flight-checkout-page]')?.dataset.demoPayment === 'true';
    const terms = form?.querySelector('[name="terms"]');
    const openButton = form?.querySelector('[data-open-booking-payment]');
    const errorBox = document.querySelector('[data-booking-error]');
    const modalElement = document.querySelector('#publicBookingConfirmationModal');
    const modal = modalElement ? window.bootstrap?.Modal.getOrCreateInstance(modalElement) : null;
    const addonInputs = [...(form?.querySelectorAll('[data-addon-price]') || [])];
    const progressTitle = document.querySelector('[data-payment-progress-title]');
    const progressCopy = document.querySelector('[data-payment-progress-copy]');
    const progressNote = document.querySelector('[data-payment-progress-note]');
    const money = value => `${openButton?.dataset.currencySymbol || ''}${(value / 100).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    const updateTotal = () => {
        const addons = addonInputs.filter(input => input.checked).reduce((sum, input) => sum + Number(input.dataset.addonPrice || 0), 0);
        const total = Number(openButton?.dataset.baseTotal || 0) + addons;
        document.querySelector('[data-confirm-total]').textContent = money(total);
        document.querySelector('[data-checkout-total]').textContent = money(total);
        const summary = document.querySelector('[data-addon-summary]');
        summary?.classList.toggle('d-none', addons === 0);
        if (summary) summary.querySelector('[data-addon-summary-value]').textContent = money(addons);
    };
    addonInputs.forEach(input => input.addEventListener('change', updateTotal));
    updateTotal();

    const bindCopyReference = root => {
        root?.querySelector('[data-copy-reference]')?.addEventListener('click', async event => {
            const value = root.querySelector('[data-copy-value]')?.textContent.trim();
            if (!value) return;
            await navigator.clipboard.writeText(value);
            event.currentTarget.innerHTML = '<i class="bi bi-check-lg"></i><span>Copied</span>';
        });
    };

    const showConfirmation = body => {
        if (!body.confirmation_html) throw new Error('The booking was created, but its confirmation could not be displayed. Open My bookings to view it.');
        const template = document.createElement('template');
        template.innerHTML = body.confirmation_html.trim();
        const confirmation = template.content.firstElementChild;
        const checkoutPage = document.querySelector('[data-flight-checkout-page]');
        if (!confirmation || !checkoutPage) throw new Error('The booking was created, but its confirmation could not be displayed. Open My bookings to view it.');

        modalElement?.addEventListener('hidden.bs.modal', () => modalElement.remove(), { once: true });
        modal?.hide();
        checkoutPage.replaceWith(confirmation);
        bindCopyReference(confirmation);
        if (body.redirect) window.history.replaceState({ bookingConfirmed: true }, '', body.redirect);
        document.title = 'Booking confirmed · Karossy Travels';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const responseBody = async response => {
        const type = response.headers.get('content-type') || '';
        if (!type.includes('application/json')) throw new Error('The secure payment service returned an unexpected response. Please retry.');
        return response.json();
    };

    const post = async (url, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                ...(payload instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
            },
            body: payload instanceof FormData ? payload : JSON.stringify(payload),
        });
        const body = await responseBody(response);
        return { response, body };
    };

    const waitForVerifiedPayment = async reference => {
        for (let attempt = 0; attempt < 60; attempt += 1) {
            if (attempt) await new Promise(resolve => window.setTimeout(resolve, 5000));
            const { response, body } = await post(form.dataset.verifyUrl, { reference });
            if (response.status === 202 && body.pending) {
                progressCopy.textContent = 'Waiting for the secure payment confirmation…';
                continue;
            }
            if (!response.ok) throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'Payment could not be verified.');
            return body;
        }
        throw new Error(`Payment is taking longer than expected. Keep your reference ${reference} and contact Karossy support if you were charged.`);
    };

    const setProgress = (title, copy, note) => {
        if (progressTitle) progressTitle.textContent = title;
        if (progressCopy) progressCopy.textContent = copy;
        if (progressNote) progressNote.textContent = note;
    };

    const resetModal = () => {
        if (openButton) openButton.disabled = false;
        setProgress('Checking the live fare', 'Validating the latest price and availability. Please do not close this page.', 'We will continue only if the itinerary and total are still valid.');
    };

    const finishPaidBooking = async reference => {
        setProgress('Finishing things up', 'Payment received. We are saving your booking and requesting the airline confirmation now.', `Please keep this page open. Your payment reference is ${reference}.`);
        modal?.show();

        try {
            showConfirmation(await waitForVerifiedPayment(reference));
        } catch (exception) {
            modal?.hide();
            resetModal();
            errorBox.textContent = exception.message;
            errorBox.classList.remove('d-none');
            errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };

    openButton?.addEventListener('click', async event => {
        event.preventDefault();
        if (!terms.checked) {
            errorBox.textContent = 'Please accept the booking conditions before continuing.';
            errorBox.classList.remove('d-none');
            terms.focus();
            return;
        }

        errorBox.classList.add('d-none');
        openButton.disabled = true;
        setProgress('Checking the live fare', 'Validating the latest price and availability before opening secure payment.', 'Please keep this page open. This normally takes only a few seconds.');
        modal?.show();

        try {
            const { response, body } = await post(form.action, new FormData(form));
            if (!response.ok) {
                const validationMessage = Object.values(body.errors || {}).flat()[0];
                const exception = new Error(validationMessage || body.message || 'Secure payment could not be started.');
                exception.redirect = body.redirect;
                throw exception;
            }
            if (body.confirmation_html) {
                showConfirmation(body);
                return;
            }
            if (!body.public_key || !body.reference) throw new Error('Secure payment could not be opened. Please retry.');
            if (typeof window.PaystackPop !== 'function') throw new Error('The secure payment window did not load. Check your connection and retry.');

            const openPaystack = () => {
                const popup = new window.PaystackPop();
                popup.newTransaction({
                    key: body.public_key,
                    email: body.email,
                    amount: body.amount_minor,
                    currency: body.currency,
                    reference: body.reference,
                    firstName: body.first_name,
                    lastName: body.last_name,
                    phone: body.phone,
                    metadata: body.metadata,
                    onSuccess: transaction => finishPaidBooking(transaction.reference || body.reference),
                    onCancel: () => {
                        resetModal();
                        errorBox.textContent = 'Payment was not completed. You can try again when you are ready.';
                        errorBox.classList.remove('d-none');
                    },
                    onError: error => {
                        resetModal();
                        errorBox.textContent = error?.message || 'The secure payment window could not be opened. Please retry.';
                        errorBox.classList.remove('d-none');
                    },
                });
            };

            if (modalElement?.classList.contains('show')) {
                modalElement.addEventListener('hidden.bs.modal', openPaystack, { once: true });
                modal.hide();
            } else {
                openPaystack();
            }
        } catch (exception) {
            resetModal();
            errorBox.textContent = exception.message;
            errorBox.classList.remove('d-none');
            if (exception.redirect) {
                window.setTimeout(() => { window.location.href = exception.redirect; }, 1600);
            } else {
                modal?.hide();
                errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
    terms?.addEventListener('change', () => {
        if (terms.checked) errorBox.classList.add('d-none');
    });
})();
</script>
@endpush

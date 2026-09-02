@extends('layouts.public')

@section('title', 'Traveller details')

@section('content')
@php
    $symbol = $currency === 'NGN' ? '₦' : ($currency === 'USD' ? '$' : $currency.' ');
    $money = fn (int $minor) => $symbol.number_format($minor / 100, 2);
@endphp
<section class="booking-page" data-flight-checkout-page data-demo-payment="{{ $demoPaymentEnabled ? 'true' : 'false' }}"><div class="container public-container">
    <a href="{{ route('flights.review', $offer) }}" class="checkout-mobile-back checkout-mobile-back-top d-lg-none"><i class="bi bi-arrow-left"></i> Back</a>
    @include('checkout._progress', ['step' => 1])
    <div class="booking-heading"><span class="public-eyebrow">Complete your booking</span><h1>Traveller details and payment</h1><p>Enter every name exactly as it appears on the passport, review the total and pay securely on this page.</p></div>

    @guest
        <div class="checkout-signin-prompt"><span><i class="bi bi-person-check"></i></span><div><strong>Sign in or create an account for a better travel experience</strong><p>Save your details, find this trip easily in My bookings and receive important travel updates. You can still continue as a guest.</p></div><button class="btn btn-outline-dark" type="button" data-bs-toggle="modal" data-bs-target="#checkoutSignInModal">Sign in or sign up</button></div>
    @endguest

    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" data-validation-summary><strong>Please check the traveller details.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="alert alert-danger d-none" data-booking-error role="alert"></div>

    <form action="{{ route('checkout.travellers.store', $offer) }}" data-payment-url="{{ route('checkout.payment.initialize', $offer) }}" data-verify-url="{{ route('checkout.payment.verify', $offer) }}" method="POST" class="checkout-travellers-layout" novalidate data-checkout-travellers-form>
        @csrf
        <div class="checkout-travellers-main">
            @foreach($types as $index => $type)
                @php($typeLabel = $type === 'ADT' ? 'Adult' : ($type === 'CNN' ? 'Child' : 'Infant'))
                <div class="booking-card traveller-card @if(!$loop->first) mt-1 @endif">
                    <div class="booking-card-title"><div><span class="traveller-number">{{ $index + 1 }}</span><div><h2>{{ $typeLabel }} traveller</h2><p>{{ $loop->first ? 'Primary passenger' : 'Passenger '.($index + 1) }}</p></div></div><div class="passport-scanner" data-passport-scanner><input class="visually-hidden" type="file" accept="image/*" capture="environment" data-passport-image><button class="btn btn-outline-dark passport-scan-button" type="button" data-scan-passport><i class="bi bi-passport"></i><span>Scan passport</span><span class="spinner-border spinner-border-sm d-none"></span></button><small class="passport-scan-status" data-passport-scan-status>Processed privately on this device</small></div></div>
                    <input type="hidden" name="travellers[{{ $index }}][type]" value="{{ $type }}">
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label">Title</label><select name="travellers[{{ $index }}][title]" class="form-select @error("travellers.$index.title") is-invalid @enderror">@foreach(['Mr','Mrs','Ms','Miss','Dr'] as $title)<option value="{{ $title }}" @selected(old("travellers.$index.title", $index === 0 ? $customer?->title : null) === $title)>{{ $title }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">First name</label><input name="travellers[{{ $index }}][first_name]" value="{{ old("travellers.$index.first_name", $index === 0 ? $customer?->first_name : null) }}" class="form-control @error("travellers.$index.first_name") is-invalid @enderror" autocomplete="given-name" placeholder="As on passport"></div>
                        <div class="col-md-5"><label class="form-label">Last name</label><input name="travellers[{{ $index }}][last_name]" value="{{ old("travellers.$index.last_name", $index === 0 ? $customer?->last_name : null) }}" class="form-control @error("travellers.$index.last_name") is-invalid @enderror" autocomplete="family-name" placeholder="As on passport"></div>
                        <div class="col-md-4"><label class="form-label">Date of birth</label><input name="travellers[{{ $index }}][date_of_birth]" value="{{ old("travellers.$index.date_of_birth", $index === 0 ? $customer?->date_of_birth?->toDateString() : null) }}" class="form-control @error("travellers.$index.date_of_birth") is-invalid @enderror" type="text" inputmode="numeric" autocomplete="bday" placeholder="dd/mm/yyyy" data-checkout-date-of-birth data-traveller-type="{{ $type }}" data-max-date="{{ $type === 'ADT' ? now()->subYears(18)->toDateString() : now()->subDay()->toDateString() }}"></div>
                        <div class="col-md-4"><label class="form-label">Gender</label><select name="travellers[{{ $index }}][gender]" class="form-select @error("travellers.$index.gender") is-invalid @enderror"><option value="">Select</option>@foreach(['male'=>'Male','female'=>'Female','unspecified'=>'Unspecified'] as $value=>$label)<option value="{{ $value }}" @selected(old("travellers.$index.gender", $index === 0 ? $customer?->gender : null) === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">Nationality code</label><input name="travellers[{{ $index }}][nationality]" value="{{ old("travellers.$index.nationality", $index === 0 ? ($customer?->nationality ?: 'NG') : 'NG') }}" class="form-control text-uppercase @error("travellers.$index.nationality") is-invalid @enderror" maxlength="2" placeholder="NG"></div>
                        <div class="col-md-4"><label class="form-label">Passport number</label><input name="travellers[{{ $index }}][passport_number]" value="{{ old("travellers.$index.passport_number", $index === 0 ? $customer?->passport_number : null) }}" class="form-control text-uppercase @error("travellers.$index.passport_number") is-invalid @enderror" autocomplete="off" placeholder="A00000000"></div>
                        <div class="col-md-4"><label class="form-label">Issuing country code</label><input name="travellers[{{ $index }}][passport_country]" value="{{ old("travellers.$index.passport_country", $index === 0 ? ($customer?->passport_country ?: 'NG') : 'NG') }}" class="form-control text-uppercase @error("travellers.$index.passport_country") is-invalid @enderror" maxlength="2" placeholder="NG"></div>
                        <div class="col-md-4"><label class="form-label">Passport expiry</label><input name="travellers[{{ $index }}][passport_expiry]" value="{{ old("travellers.$index.passport_expiry", $index === 0 ? $customer?->passport_expires_at?->toDateString() : null) }}" class="form-control @error("travellers.$index.passport_expiry") is-invalid @enderror" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" data-checkout-passport-expiry></div>
                    </div>
                </div>
            @endforeach

            <div class="booking-card mt-1">
                <div class="booking-card-title"><div><span class="traveller-number"><i class="bi bi-envelope"></i></span><div><h2>Contact details</h2><p>The confirmation and any schedule changes will be sent here.</p></div></div></div>
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label">Email address</label><input name="contact[email]" type="email" class="form-control @error('contact.email') is-invalid @enderror" value="{{ old('contact.email', $customer?->email ?: auth()->user()?->email) }}" autocomplete="email">@error('contact.email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label">Mobile number</label><x-phone-input name="contact[phone]" code-name="contact[phone_code]" :value="$customer?->phone" class="@error('contact.phone') is-invalid @enderror" />@error('contact.phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                </div>
                <label class="form-check mt-3"><input name="notifications" value="1" class="form-check-input" type="checkbox" @checked(old('notifications', true))><span class="form-check-label">Send schedule changes and ticket updates to this contact.</span></label>
            </div>

            @include('checkout._options')

        </div>
        <aside class="checkout-travellers-summary">@include('checkout._summary')</aside>
        <div class="checkout-actions"><a href="{{ route('flights.review', $offer) }}" class="btn btn-outline-dark checkout-desktop-back d-none d-lg-inline-flex"><i class="bi bi-arrow-left"></i> Back</a><button class="btn btn-karossy checkout-pay-button" type="submit" data-open-booking-payment data-base-total="{{ $total['amount_minor'] }}" data-currency-symbol="{{ $symbol }}"><span data-submit-label>Pay <b data-confirm-total>{{ $money($total['amount_minor']) }}</b></span><span class="spinner-border spinner-border-sm d-none" data-submit-spinner></span> <i class="bi bi-arrow-right"></i></button></div>
    </form>
</div></section>

<section class="booking-finalization-screen" data-booking-finalization-screen hidden aria-live="polite" aria-busy="true">
    <div class="booking-finalization-card">
        <span class="booking-finalization-icon" aria-hidden="true"><i class="bi bi-airplane"></i></span>
        <span class="public-eyebrow">Payment successful</span>
        <h2 data-finalization-title>Finishing your booking</h2>
        <p data-finalization-copy>Your payment is confirmed. We are now securing your reservation and creating your booking reference.</p>
        <div class="booking-finalization-progress" role="progressbar" aria-label="Booking completion progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="12" data-finalization-progress>
            <span data-finalization-progress-bar style="width: 12%"></span>
        </div>
        <div class="booking-finalization-status">
            <strong data-finalization-status>Confirming your payment</strong>
            <span data-finalization-percent>12%</span>
        </div>
        <small data-finalization-note>Please keep this page open. This normally takes only a moment.</small>
    </div>
</section>

<div class="modal fade flight-revalidation-modal" id="publicBookingConfirmationModal" tabindex="-1" aria-labelledby="publicBookingProgressTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center"><div data-booking-progress><span class="revalidation-icon"><i class="bi bi-shield-check"></i></span><h2 id="publicBookingProgressTitle" data-payment-progress-title>Checking the live fare</h2><p data-payment-progress-copy>Validating the latest price and availability. Please do not close this page.</p><div class="revalidation-progress" aria-hidden="true"><span></span></div><small data-payment-progress-note>Secure payment will open as soon as the exact total is confirmed.</small></div></div></div></div></div>

@guest
<div class="modal fade checkout-signin-modal" id="checkoutSignInModal" tabindex="-1" aria-labelledby="checkoutSignInTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><div><span class="modal-eyebrow">Optional, but helpful</span><h2 class="modal-title" id="checkoutSignInTitle">A better experience with your Karossy account</h2><p>Sign in or create an account without leaving this checkout.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div class="checkout-account-tabs nav nav-pills" role="tablist">
                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#checkout-signin-pane" type="button" role="tab">Sign in</button>
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#checkout-register-pane" type="button" role="tab">Create account</button>
            </div>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="checkout-signin-pane" role="tabpanel">
                    <form action="{{ route('login.store') }}" method="POST" data-checkout-login novalidate>
                        @csrf<div class="alert alert-danger d-none" data-login-error></div>
                        <label class="form-label" for="checkout-login-email">Email address</label><input class="form-control mb-3" id="checkout-login-email" name="email" type="email" autocomplete="email">
                        <label class="form-label" for="checkout-login-password">Password</label><input class="form-control" id="checkout-login-password" name="password" type="password" autocomplete="current-password">
                        <label class="form-check mt-3"><input class="form-check-input" name="remember" value="1" type="checkbox"><span class="form-check-label">Keep me signed in</span></label>
                        <button class="btn btn-karossy checkout-account-submit mt-4" type="submit"><span>Sign in and continue</span><span class="spinner-border spinner-border-sm d-none"></span></button>
                    </form>
                </div>
                <div class="tab-pane fade" id="checkout-register-pane" role="tabpanel">
                    <form action="{{ route('register.store') }}" method="POST" data-checkout-register novalidate>
                        @csrf<input type="hidden" name="currency_code" value="{{ in_array(session('display_currency'), ['NGN', 'USD'], true) ? session('display_currency') : 'NGN' }}">
                        <div class="alert alert-danger d-none" data-register-error></div>
                        <div class="row g-3"><div class="col-sm-6"><label class="form-label" for="checkout-register-first-name">First name</label><input class="form-control" id="checkout-register-first-name" name="first_name" autocomplete="given-name"></div><div class="col-sm-6"><label class="form-label" for="checkout-register-last-name">Last name</label><input class="form-control" id="checkout-register-last-name" name="last_name" autocomplete="family-name"></div></div>
                        <label class="form-label mt-3" for="checkout-register-email">Email address</label><input class="form-control" id="checkout-register-email" name="email" type="email" autocomplete="email">
                        <label class="form-label mt-3" for="checkout-register-phone">Mobile number <span class="text-muted">(optional)</span></label><input class="form-control" id="checkout-register-phone" name="phone" autocomplete="tel">
                        <div class="row g-3 mt-0"><div class="col-sm-6"><label class="form-label" for="checkout-register-password">Password</label><input class="form-control" id="checkout-register-password" name="password" type="password" autocomplete="new-password"></div><div class="col-sm-6"><label class="form-label" for="checkout-register-confirmation">Confirm password</label><input class="form-control" id="checkout-register-confirmation" name="password_confirmation" type="password" autocomplete="new-password"></div></div>
                        <label class="form-check mt-3"><input class="form-check-input" name="terms" value="1" type="checkbox"><span class="form-check-label">I agree to the terms of service and privacy policy.</span></label>
                        <button class="btn btn-karossy checkout-account-submit mt-4" type="submit"><span>Create account and continue</span><span class="spinner-border spinner-border-sm d-none"></span></button>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal-footer"><span>Your traveller details will remain on this page.</span><button class="btn btn-outline-dark" type="button" data-bs-dismiss="modal">Continue as guest</button></div>
    </div></div>
</div>
@endguest
@endsection

@push('scripts')
@unless($demoPaymentEnabled)<script src="https://js.paystack.co/v2/inline.js"></script>@endunless
<script>
(() => {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const clearErrors = form => {
        form.querySelectorAll('.is-invalid').forEach(field => field.classList.remove('is-invalid'));
        form.querySelectorAll('[data-ajax-error]').forEach(error => error.remove());
        document.querySelector('[data-validation-summary]')?.classList.add('d-none');
    };
    const clearFieldError = field => {
        if (!(field instanceof HTMLElement) || !field.matches('input, select, textarea')) return;
        field.classList.remove('is-invalid');
        const feedback = field.parentElement?.querySelector('.invalid-feedback');
        if (feedback?.dataset.ajaxError === 'true') feedback.remove();
        else feedback?.classList.add('d-none');
        document.querySelector('[data-validation-summary]')?.classList.add('d-none');
    };
    const fieldName = name => name.split('.').reduce((result, part, index) => index === 0 ? part : `${result}[${part}]`, '');
    const showErrors = (form, errors = {}) => Object.entries(errors).forEach(([name, messages]) => {
        const field = form.elements.namedItem(fieldName(name)) || form.elements.namedItem(name);
        if (!(field instanceof HTMLElement)) return;
        field.classList.add('is-invalid');
        const error = document.createElement('div');
        error.className = 'invalid-feedback d-block';
        error.dataset.ajaxError = 'true';
        error.textContent = Array.isArray(messages) ? messages[0] : messages;
        field.insertAdjacentElement('afterend', error);
    });
    const clientErrors = form => {
        const errors = {};
        const today = new Date().toISOString().slice(0, 10);
        const adultCutoff = new Date();
        adultCutoff.setFullYear(adultCutoff.getFullYear() - 18);
        const adultCutoffValue = `${adultCutoff.getFullYear()}-${String(adultCutoff.getMonth() + 1).padStart(2, '0')}-${String(adultCutoff.getDate()).padStart(2, '0')}`;
        const namePattern = /^[\p{L}][\p{L}\p{M}'’\-]*(?: [\p{L}][\p{L}\p{M}'’\-]*)*$/u;
        [...form.querySelectorAll('.traveller-card')].forEach((card, index) => {
            const value = field => String(form.elements.namedItem(`travellers[${index}][${field}]`)?.value || '').trim();
            ['first_name', 'last_name'].forEach(field => {
                if (!namePattern.test(value(field))) errors[`travellers.${index}.${field}`] = ['Use letters, spaces, apostrophes or hyphens exactly as shown on the passport.'];
            });
            ['gender', 'nationality', 'passport_number', 'passport_country'].forEach(field => {
                if (!value(field)) errors[`travellers.${index}.${field}`] = ['This field is required.'];
            });
            if (!value('date_of_birth') || value('date_of_birth') >= today) errors[`travellers.${index}.date_of_birth`] = ['Enter a valid date of birth before today.'];
            else if (value('type') === 'ADT' && value('date_of_birth') > adultCutoffValue) errors[`travellers.${index}.date_of_birth`] = ['Adult travellers must be at least 18 years old on the booking date.'];
            if (!value('passport_expiry') || value('passport_expiry') <= today) errors[`travellers.${index}.passport_expiry`] = ['Passport expiry must be after today.'];
        });
        const email = String(form.elements.namedItem('contact[email]')?.value || '').trim();
        const phone = String(form.elements.namedItem('contact[phone]')?.value || '').trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors['contact.email'] = ['Enter a valid email address.'];
        if (!/^[0-9 +()\-]{7,24}$/.test(phone)) errors['contact.phone'] = ['Enter a valid mobile number.'];
        if (!form.elements.namedItem('terms')?.checked) errors.terms = ['Accept the booking conditions before paying.'];
        return errors;
    };

    const checkoutForm = document.querySelector('[data-checkout-travellers-form]');
    const payButton = checkoutForm?.querySelector('[data-open-booking-payment]');
    const errorBox = document.querySelector('[data-booking-error]');
    const modalElement = document.querySelector('#publicBookingConfirmationModal');
    const paymentModal = modalElement ? window.bootstrap?.Modal.getOrCreateInstance(modalElement) : null;
    const progressTitle = document.querySelector('[data-payment-progress-title]');
    const progressCopy = document.querySelector('[data-payment-progress-copy]');
    const progressNote = document.querySelector('[data-payment-progress-note]');
    const finalizationScreen = document.querySelector('[data-booking-finalization-screen]');
    const finalizationTitle = finalizationScreen?.querySelector('[data-finalization-title]');
    const finalizationCopy = finalizationScreen?.querySelector('[data-finalization-copy]');
    const finalizationStatus = finalizationScreen?.querySelector('[data-finalization-status]');
    const finalizationPercent = finalizationScreen?.querySelector('[data-finalization-percent]');
    const finalizationNote = finalizationScreen?.querySelector('[data-finalization-note]');
    const finalizationProgress = finalizationScreen?.querySelector('[data-finalization-progress]');
    const finalizationProgressBar = finalizationScreen?.querySelector('[data-finalization-progress-bar]');
    let finalizationTimer = null;
    let finalizationValue = 12;
    const addonInputs = [...(checkoutForm?.querySelectorAll('[data-addon-price]') || [])];
    const money = value => `${payButton?.dataset.currencySymbol || ''}${(value / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const setProgress = (title, copy, note) => {
        if (progressTitle) progressTitle.textContent = title;
        if (progressCopy) progressCopy.textContent = copy;
        if (progressNote) progressNote.textContent = note;
    };
    const setFinalizationProgress = (value, status, copy = null) => {
        finalizationValue = Math.max(finalizationValue, Math.min(100, Math.round(value)));
        if (finalizationProgressBar) finalizationProgressBar.style.width = `${finalizationValue}%`;
        if (finalizationProgress) finalizationProgress.setAttribute('aria-valuenow', String(finalizationValue));
        if (finalizationPercent) finalizationPercent.textContent = `${finalizationValue}%`;
        if (status && finalizationStatus) finalizationStatus.textContent = status;
        if (copy && finalizationCopy) finalizationCopy.textContent = copy;
    };
    const beginFinalization = reference => {
        paymentModal?.hide();
        finalizationValue = 12;
        const checkoutPage = document.querySelector('[data-flight-checkout-page]');
        if (checkoutPage) {
            checkoutPage.hidden = true;
            checkoutPage.setAttribute('aria-hidden', 'true');
            checkoutPage.setAttribute('inert', '');
        }
        document.body.classList.add('booking-is-finalizing');
        if (finalizationScreen) {
            finalizationScreen.hidden = false;
            window.requestAnimationFrame(() => finalizationScreen.classList.add('is-visible'));
        }
        if (finalizationTitle) finalizationTitle.textContent = 'Finishing your booking';
        if (finalizationNote) finalizationNote.textContent = `Please keep this page open. Payment reference: ${reference}.`;
        setFinalizationProgress(18, 'Payment received', 'Your payment is confirmed. We are now securing your reservation and creating your booking reference.');
        window.clearInterval(finalizationTimer);
        finalizationTimer = window.setInterval(() => {
            const increment = finalizationValue < 55 ? 4 : (finalizationValue < 78 ? 2 : 1);
            if (finalizationValue < 92) setFinalizationProgress(finalizationValue + increment, finalizationStatus?.textContent || 'Finishing your booking');
        }, 900);
    };
    const endFinalization = () => {
        window.clearInterval(finalizationTimer);
        finalizationTimer = null;
        document.body.classList.remove('booking-is-finalizing');
        if (finalizationScreen) {
            finalizationScreen.classList.remove('is-visible');
            finalizationScreen.hidden = true;
        }
    };
    const restoreCheckout = () => {
        const checkoutPage = document.querySelector('[data-flight-checkout-page]');
        if (checkoutPage) {
            checkoutPage.hidden = false;
            checkoutPage.removeAttribute('aria-hidden');
            checkoutPage.removeAttribute('inert');
        }
        endFinalization();
    };
    const updateTotal = () => {
        const addons = addonInputs.filter(input => input.checked).reduce((sum, input) => sum + Number(input.dataset.addonPrice || 0), 0);
        const total = Number(payButton?.dataset.baseTotal || 0) + addons;
        document.querySelector('[data-confirm-total]').textContent = money(total);
        document.querySelector('[data-checkout-total]').textContent = money(total);
        const summary = document.querySelector('[data-addon-summary]');
        summary?.classList.toggle('d-none', addons === 0);
        if (summary) summary.querySelector('[data-addon-summary-value]').textContent = money(addons);
    };
    addonInputs.forEach(input => input.addEventListener('change', updateTotal));
    updateTotal();

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
                'X-CSRF-TOKEN': csrf(),
                ...(payload instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
            },
            body: payload instanceof FormData ? payload : JSON.stringify(payload),
        });
        return { response, body: await responseBody(response) };
    };
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
        paymentModal?.hide();
        const revealConfirmation = () => {
            checkoutPage.replaceWith(confirmation);
            bindCopyReference(confirmation);
            endFinalization();
            if (body.redirect) window.history.replaceState({ bookingConfirmed: true }, '', body.redirect);
            document.title = 'Booking confirmed · Karossy Travels';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };
        if (finalizationScreen && !finalizationScreen.hidden) {
            setFinalizationProgress(100, 'Booking confirmed', 'Your reservation is complete. We are opening your booking confirmation now.');
            if (finalizationTitle) finalizationTitle.textContent = 'Your booking is ready';
            if (finalizationNote) finalizationNote.textContent = 'Your confirmation and receipt have been sent to your email address.';
            window.setTimeout(revealConfirmation, 650);
            return;
        }
        revealConfirmation();
    };
    const waitForVerifiedPayment = async (reference, transactionId = null) => {
        for (let attempt = 0; attempt < 60; attempt += 1) {
            if (attempt) await new Promise(resolve => window.setTimeout(resolve, 5000));
            const { response, body } = await post(checkoutForm.dataset.verifyUrl, { reference, transaction_id: transactionId });
            if (response.status === 202 && body.pending) {
                setFinalizationProgress(35, 'Confirming your payment', 'We are waiting for the secure payment confirmation. You do not need to pay again.');
                continue;
            }
            if (!response.ok) throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'Payment could not be verified.');
            setFinalizationProgress(86, 'Creating your booking reference', 'Your payment is confirmed and the reservation is being completed now.');
            return body;
        }
        throw new Error(`Payment is taking longer than expected. Keep your reference ${reference} and contact Karossy support if you were charged.`);
    };
    const finishPaidBooking = async (reference, transactionId = null) => {
        beginFinalization(reference);
        try {
            showConfirmation(await waitForVerifiedPayment(reference, transactionId));
        } catch (error) {
            restoreCheckout();
            payButton.disabled = false;
            errorBox.textContent = error.message;
            errorBox.classList.remove('d-none');
            errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };
    const openPaystack = body => {
        if (!body.public_key || !body.reference) throw new Error('Secure payment could not be opened. Please retry.');
        if (typeof window.PaystackPop !== 'function') throw new Error('The secure payment window did not load. Check your connection and retry.');
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
            onSuccess: transaction => finishPaidBooking(
                transaction.reference || transaction.trxref || body.reference,
                transaction.transaction || transaction.trans || null,
            ),
            onCancel: () => {
                payButton.disabled = false;
                errorBox.textContent = 'Payment was not completed. You can try again when you are ready.';
                errorBox.classList.remove('d-none');
            },
            onError: error => {
                payButton.disabled = false;
                errorBox.textContent = error?.message || 'The secure payment window could not be opened. Please retry.';
                errorBox.classList.remove('d-none');
            },
        });
    };

    document.querySelectorAll('[data-checkout-travellers-form], [data-checkout-login], [data-checkout-register]').forEach(form => {
        const clearCurrentError = event => {
            clearFieldError(event.target);
            form.querySelector('[data-login-error], [data-register-error]')?.classList.add('d-none');
        };
        form.addEventListener('input', clearCurrentError);
        form.addEventListener('change', clearCurrentError);
    });

    document.querySelector('[data-checkout-travellers-form]')?.addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = form.querySelector('[type="submit"]');
        const spinner = button.querySelector('.spinner-border');
        clearErrors(form);
        const localErrors = clientErrors(form);
        if (Object.keys(localErrors).length) {
            showErrors(form, localErrors);
            const first = form.querySelector('.is-invalid');
            first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            first?.focus();
            return;
        }
        button.disabled = true;
        spinner?.classList.remove('d-none');
        errorBox.classList.add('d-none');
        setProgress('Checking the live fare', 'Your details are saved on this page while we confirm the latest airline price and availability.', 'Secure payment will open automatically as soon as the exact total is confirmed.');
        paymentModal?.show();
        try {
            const saved = await post(form.action, new FormData(form));
            if (!saved.response.ok) {
                showErrors(form, saved.body.errors);
                throw new Error(saved.body.message || 'Please check the highlighted details.');
            }
            const initialized = await post(form.dataset.paymentUrl, new FormData(form));
            if (!initialized.response.ok) {
                showErrors(form, initialized.body.errors);
                throw new Error(Object.values(initialized.body.errors || {}).flat()[0] || initialized.body.message || 'Secure payment could not be started.');
            }
            if (initialized.body.confirmation_html) {
                showConfirmation(initialized.body);
                return;
            }
            paymentModal?.hide();
            openPaystack(initialized.body);
        } catch (error) {
            paymentModal?.hide();
            errorBox.textContent = error.message;
            errorBox.classList.remove('d-none');
            const first = form.querySelector('.is-invalid');
            (first || errorBox)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            first?.focus();
            button.disabled = false;
        } finally {
            spinner?.classList.add('d-none');
        }
    });

    document.querySelector('[data-checkout-login]')?.addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget; const button = form.querySelector('[type="submit"]'); const spinner = button.querySelector('.spinner-border'); const errorBox = form.querySelector('[data-login-error]');
        errorBox.classList.add('d-none'); button.disabled = true; spinner.classList.remove('d-none');
        try {
            const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() }, body: new FormData(form) });
            const body = await response.json();
            if (!response.ok) throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'Sign in failed.');
            document.querySelector('meta[name="csrf-token"]').content = body.csrf_token;
            window.bootstrap?.Modal.getInstance(document.querySelector('#checkoutSignInModal'))?.hide();
            document.querySelector('.checkout-signin-prompt').innerHTML = `<span><i class="bi bi-check-lg"></i></span><div><strong>Signed in as ${body.user.name}</strong><p>Your booking will be linked to ${body.user.email}.</p></div>`;
        } catch (error) { errorBox.textContent = error.message; errorBox.classList.remove('d-none'); }
        finally { button.disabled = false; spinner.classList.add('d-none'); }
    });

    document.querySelector('[data-checkout-register]')?.addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget; const button = form.querySelector('[type="submit"]'); const spinner = button.querySelector('.spinner-border'); const errorBox = form.querySelector('[data-register-error]');
        const data = new FormData(form); const firstName = String(data.get('first_name') || '').trim(); const lastName = String(data.get('last_name') || '').trim(); const email = String(data.get('email') || '').trim(); const password = String(data.get('password') || '');
        let message = '';
        if (!firstName || !lastName) message = 'Enter your first and last name.';
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) message = 'Enter a valid email address.';
        else if (password.length < 8 || !/[A-Za-z]/.test(password) || !/\d/.test(password)) message = 'Use at least 8 characters with letters and numbers.';
        else if (password !== String(data.get('password_confirmation') || '')) message = 'The passwords do not match.';
        else if (!data.get('terms')) message = 'Accept the terms to create your account.';
        if (message) { errorBox.textContent = message; errorBox.classList.remove('d-none'); return; }
        errorBox.classList.add('d-none'); button.disabled = true; spinner.classList.remove('d-none');
        try {
            const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() }, body: data });
            const body = await response.json();
            if (!response.ok) throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'Your account could not be created.');
            document.querySelector('meta[name="csrf-token"]').content = body.csrf_token;
            window.bootstrap?.Modal.getInstance(document.querySelector('#checkoutSignInModal'))?.hide();
            document.querySelector('.checkout-signin-prompt').innerHTML = `<span><i class="bi bi-check-lg"></i></span><div><strong>Welcome, ${body.user.name}</strong><p>Your new account is ready and this booking will be saved to My bookings.</p></div>`;
        } catch (error) { errorBox.textContent = error.message; errorBox.classList.remove('d-none'); }
        finally { button.disabled = false; spinner.classList.add('d-none'); }
    });
})();
</script>
@endpush

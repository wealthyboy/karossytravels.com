@extends('layouts.admin')
@section('title', 'Complete flight booking')
@section('content')
<header class="mb-4"><p class="text-danger fw-semibold mb-1">FLIGHT OPERATIONS</p><h1 class="h3 fw-bold mb-2">Complete flight booking</h1><p class="text-secondary mb-0">The fare has been revalidated. No payment is collected in the admin workflow.</p></header>

<div class="alert alert-danger d-none" id="booking-error-banner" role="alert"></div>
<div class="alert alert-success d-none" id="booking-success-banner" role="alert"></div>

<form action="{{ route('admin.flights.orders.store', $offer) }}" method="POST" class="admin-booking-form" id="booking-form">@csrf
    <div class="row g-4"><div class="col-xl-8">
        <section class="admin-panel-card"><div class="admin-panel-title"><div><span>1</span><div><h2>Booking customer</h2><p>Search for and select the customer who owns this booking.</p></div></div><button class="btn btn-karossy btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addBookingCustomerModal"><i class="bi bi-person-plus"></i> Add customer</button></div>
            <label class="form-label" for="customer_search">Select customer</label>
            <div class="booking-customer-picker" data-customer-picker>
                <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" id="customer_search" type="search" placeholder="Search name, email or phone" autocomplete="off" data-customer-search></div>
                <input type="hidden" id="customer_id" name="customer_id" value="{{ old('customer_id') }}" required data-customer-id>
                <div class="booking-customer-results" data-customer-results>
                    @forelse($customers as $customer)<button type="button" class="booking-customer-option @if(old('customer_id')===$customer->id) selected @endif" data-customer-option data-id="{{ $customer->id }}" data-search="{{ strtolower($customer->full_name.' '.$customer->email.' '.$customer->phone) }}" data-name="{{ $customer->full_name }}" data-email="{{ $customer->email }}"><span class="booking-customer-avatar">{{ strtoupper(substr($customer->first_name, 0, 1).substr($customer->last_name, 0, 1)) }}</span><span><strong>{{ $customer->full_name }}</strong><small>{{ $customer->email }}@if($customer->phone) · {{ $customer->phone }}@endif</small></span><i class="bi bi-check-circle-fill"></i></button>@empty<div class="booking-customer-empty">No customers yet. Add the first customer.</div>@endforelse
                </div>
                <div class="booking-selected-customer d-none" data-selected-customer><span><i class="bi bi-person-check"></i></span><div><small>Selected customer</small><strong data-selected-customer-name></strong><p data-selected-customer-email></p></div><button type="button" class="btn btn-link" data-change-customer>Change</button></div>
            </div>
            @error('customer_id')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        </section>
        <section class="admin-panel-card mt-3"><div class="admin-panel-title"><div><span>0</span><div><h2>Agency</h2><p>Optional: supply your agency/customer number for ticketing.</p></div></div></div>
            <label class="form-label" for="agency_number">Agency customer number</label>
            <input class="form-control text-uppercase" id="agency_number" name="agency_number" value="{{ old('agency_number', config('services.travel.travel_api.agency_number')) }}" maxlength="10" placeholder="e.g. 1234567">
            @error('agency_number')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        </section>
        <section class="admin-panel-card mt-4"><div class="admin-panel-title"><div><span>2</span><div><h2>Passenger details</h2><p>Enter each name exactly as shown on the passport.</p></div></div></div>
            @foreach($types as $index => $type)<div class="admin-traveller-block"><div class="admin-traveller-heading"><strong>Passenger {{ $index + 1 }}</strong><span>{{ $type === 'ADT' ? 'Adult' : ($type === 'CNN' ? 'Child' : 'Infant') }}</span></div><input type="hidden" name="travellers[{{ $index }}][type]" value="{{ $type }}"><div class="row g-3">
                <div class="col-md-2"><label class="form-label">Title</label><select class="form-select" name="travellers[{{ $index }}][title]" required>@foreach(['Mr','Mrs','Ms','Miss','Dr'] as $title)<option @selected(old("travellers.$index.title")===$title)>{{ $title }}</option>@endforeach</select></div>
                <div class="col-md-5"><label class="form-label">First name</label><input class="form-control" name="travellers[{{ $index }}][first_name]" value="{{ old("travellers.$index.first_name") }}" required></div>
                <div class="col-md-5"><label class="form-label">Last name</label><input class="form-control" name="travellers[{{ $index }}][last_name]" value="{{ old("travellers.$index.last_name") }}" required></div>
                <div class="col-md-4"><label class="form-label">Date of birth</label><input class="form-control" type="date" name="travellers[{{ $index }}][date_of_birth]" value="{{ old("travellers.$index.date_of_birth") }}" required></div>
                <div class="col-md-4"><label class="form-label">Gender</label><select class="form-select" name="travellers[{{ $index }}][gender]" required><option value="male">Male</option><option value="female">Female</option><option value="unspecified">Unspecified</option></select></div>
                <div class="col-md-4"><label class="form-label">Nationality code</label><input class="form-control text-uppercase" maxlength="2" name="travellers[{{ $index }}][nationality]" value="{{ old("travellers.$index.nationality", 'NG') }}" required></div>
                <div class="col-md-4"><label class="form-label">Passport number</label><input class="form-control text-uppercase" name="travellers[{{ $index }}][passport_number]" value="{{ old("travellers.$index.passport_number") }}" required></div>
                <div class="col-md-4"><label class="form-label">Issuing country</label><input class="form-control text-uppercase" maxlength="2" name="travellers[{{ $index }}][passport_country]" value="{{ old("travellers.$index.passport_country", 'NG') }}" required></div>
                <div class="col-md-4"><label class="form-label">Passport expiry</label><input class="form-control" type="date" name="travellers[{{ $index }}][passport_expiry]" value="{{ old("travellers.$index.passport_expiry") }}" required></div>
            </div></div>@endforeach
        </section>
    </div><aside class="col-xl-4"><section class="admin-panel-card sticky-xl-top admin-order-summary" data-price-summary data-base-minor="{{ $offer->selling_total_minor }}"><span class="admin-eyebrow">REVALIDATED FARE</span><h2>{{ data_get($offer->itinerary, '0.origin') }} to {{ data_get($offer->itinerary, (count($offer->itinerary)-1).'.destination') }}</h2><p>{{ count($offer->itinerary) }} segment(s) · {{ strtoupper(data_get($offer->fare_summary, 'validating_airline', $offer->provider)) }}</p>
        <div class="admin-summary-line"><span>Confirmed fare</span><strong>{{ $offer->currency }} {{ number_format($offer->selling_total_minor/100, 2) }}</strong></div>
        @if($addons->isNotEmpty())<hr><h3 class="h6">Optional add-ons</h3>@foreach($addons as $addon)<label class="d-flex gap-2 align-items-start py-2"><input class="form-check-input mt-1" type="checkbox" name="addons[]" value="{{ $addon->id }}" data-addon-minor="{{ $addon->display_price_minor }}" @checked(in_array($addon->id, old('addons', []), true))><span><strong class="d-block">{{ $addon->title }}</strong><small class="text-secondary">{{ $addon->description }} · {{ $offer->currency }} {{ number_format($addon->display_price_minor/100, 2) }}</small></span></label>@endforeach @endif
        <hr><h3 class="h6">Agent price adjustment</h3><p class="small text-secondary">Optional markup added only to this booking.</p><div class="row g-2"><div class="col-6"><select class="form-select" name="operator_markup_type" data-markup-type><option value="none">No markup</option><option value="fixed" @selected(old('operator_markup_type')==='fixed')>Fixed</option><option value="percentage" @selected(old('operator_markup_type')==='percentage')>Percentage</option></select></div><div class="col-6"><input class="form-control" type="number" name="operator_markup_value" min="0" step="0.01" value="{{ old('operator_markup_value') }}" placeholder="Value" data-markup-value></div></div>
        <div class="admin-summary-line mt-3"><span>Customer total</span><strong data-customer-total>{{ $offer->currency }} {{ number_format($offer->selling_total_minor/100, 2) }}</strong></div><small><i class="bi bi-clock"></i> Revalidated {{ $offer->last_validated_at?->diffForHumans() }}</small><button class="btn btn-karossy w-100 mt-3" type="submit"><span data-submit-label>Create booking</span><span class="spinner-border spinner-border-sm d-none" data-submit-spinner></span></button><p class="admin-no-payment"><i class="bi bi-shield-check"></i> Add-ons and agent markup are included in the recorded customer total.</p></section></aside></div>
</form>

<div class="modal fade" id="addBookingCustomerModal" tabindex="-1" aria-labelledby="addBookingCustomerTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content booking-customer-modal"><div class="modal-header"><div><span class="admin-eyebrow">NEW CUSTOMER</span><h2 class="modal-title" id="addBookingCustomerTitle">Add booking customer</h2><p>Create the customer and select them for this booking.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><form data-add-booking-customer action="{{ route('admin.customers.store') }}" method="POST"><div class="modal-body"><div class="alert alert-danger d-none" data-customer-form-error></div><div class="row g-3">
    <div class="col-md-2"><label class="form-label">Title</label><select class="form-select" name="title"><option value="">—</option>@foreach(['Mr','Mrs','Ms','Miss','Dr','Prof'] as $title)<option>{{ $title }}</option>@endforeach</select></div>
    <div class="col-md-5"><label class="form-label">First name</label><input class="form-control" name="first_name" required></div><div class="col-md-5"><label class="form-label">Last name</label><input class="form-control" name="last_name" required></div>
    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div><div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" type="tel" name="phone"></div>
    <div class="col-md-4"><label class="form-label">Date of birth</label><input class="form-control" type="date" name="date_of_birth" max="{{ now()->subDay()->toDateString() }}"></div><div class="col-md-4"><label class="form-label">Gender</label><select class="form-select" name="gender"><option value="">Not specified</option><option value="male">Male</option><option value="female">Female</option><option value="unspecified">Prefer not to say</option></select></div><div class="col-md-4"><label class="form-label">Nationality code</label><input class="form-control text-uppercase" name="nationality" maxlength="2" placeholder="NG"></div>
    <input type="hidden" name="status" value="active">
</div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-karossy" type="submit"><span data-customer-submit-label>Create and select customer</span><span class="spinner-border spinner-border-sm d-none" data-customer-submit-spinner></span></button></div></form></div></div></div>
@endsection
@push('scripts')<script>
document.addEventListener('DOMContentLoaded', () => {

    const priceSummary = document.querySelector('[data-price-summary]');
    if (priceSummary) {
        const total = priceSummary.querySelector('[data-customer-total]');
        const type = priceSummary.querySelector('[data-markup-type]');
        const value = priceSummary.querySelector('[data-markup-value]');
        const calculate = () => {
            const base = Number(priceSummary.dataset.baseMinor || 0);
            const addons = [...priceSummary.querySelectorAll('[data-addon-minor]:checked')].reduce((sum, input) => sum + Number(input.dataset.addonMinor || 0), 0);
            const entered = Math.max(0, Number(value.value || 0));
            const adjustment = type.value === 'fixed' ? Math.round(entered * 100) : (type.value === 'percentage' ? Math.round((base + addons) * entered / 100) : 0);
            total.textContent = '{{ $offer->currency }} ' + ((base + addons + adjustment) / 100).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            value.disabled = type.value === 'none';
        };
        priceSummary.addEventListener('input', calculate);
        priceSummary.addEventListener('change', calculate);
        calculate();
    }

    /* ─── Customer picker ─── */
    const picker = document.querySelector('[data-customer-picker]');
    if (!picker) return;
    const search   = picker.querySelector('[data-customer-search]');
    const idInput  = picker.querySelector('[data-customer-id]');
    const results  = picker.querySelector('[data-customer-results]');
    const selected = picker.querySelector('[data-selected-customer]');

    const choose = option => {
        idInput.value = option.dataset.id;
        selected.querySelector('[data-selected-customer-name]').textContent  = option.dataset.name;
        selected.querySelector('[data-selected-customer-email]').textContent = option.dataset.email;
        results.classList.add('d-none');
        search.closest('.input-group').classList.add('d-none');
        selected.classList.remove('d-none');
    };

    results.addEventListener('click', event => {
        const option = event.target.closest('[data-customer-option]');
        if (option) choose(option);
    });

    search.addEventListener('input', () => {
        const query = search.value.trim().toLowerCase();
        results.querySelectorAll('[data-customer-option]').forEach(o => o.classList.toggle('d-none', query && !o.dataset.search.includes(query)));
    });

    picker.querySelector('[data-change-customer]').addEventListener('click', () => {
        idInput.value = '';
        selected.classList.add('d-none');
        results.classList.remove('d-none');
        search.closest('.input-group').classList.remove('d-none');
        search.focus();
    });

    const initial = results.querySelector(`[data-customer-option][data-id="${CSS.escape(idInput.value)}"]`);
    if (initial) choose(initial);

    /* ─── Add customer modal (AJAX) ─── */
    const addCustomerForm = document.querySelector('[data-add-booking-customer]');
    addCustomerForm.addEventListener('submit', async event => {
        event.preventDefault();
        const errorBox = addCustomerForm.querySelector('[data-customer-form-error]');
        const btn      = addCustomerForm.querySelector('button[type="submit"]');
        errorBox.classList.add('d-none');
        btn.disabled = true;
        addCustomerForm.querySelector('[data-customer-submit-spinner]').classList.remove('d-none');
        try {
            const response = await fetch(addCustomerForm.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: new FormData(addCustomerForm),
            });
            const body = await response.json();
            if (!response.ok) {
                const messages = Object.values(body.errors || {}).flat();
                throw new Error(messages[0] || body.message || 'Customer could not be created.');
            }
            const customer = body.data;
            const option = document.createElement('button');
            option.type = 'button'; option.className = 'booking-customer-option';
            option.dataset.customerOption = ''; option.dataset.id = customer.id;
            option.dataset.search = `${customer.name} ${customer.email} ${customer.phone || ''}`.toLowerCase();
            option.dataset.name = customer.name; option.dataset.email = customer.email;
            option.innerHTML = `<span class="booking-customer-avatar">${customer.name.split(/\s+/).map(p => p[0]).slice(0,2).join('').toUpperCase()}</span><span><strong></strong><small></small></span><i class="bi bi-check-circle-fill"></i>`;
            option.querySelector('strong').textContent = customer.name;
            option.querySelector('small').textContent  = customer.email + (customer.phone ? ` · ${customer.phone}` : '');
            results.prepend(option);
            choose(option);
            addCustomerForm.reset();
            document.querySelector('#addBookingCustomerModal').querySelector('[data-bs-dismiss="modal"]').click();
        } catch (err) {
            errorBox.textContent = err.message;
            errorBox.classList.remove('d-none');
        } finally {
            btn.disabled = false;
            addCustomerForm.querySelector('[data-customer-submit-spinner]').classList.add('d-none');
        }
    });

    /* ─── Booking form (AJAX — no page reload) ─── */
    const bookingForm  = document.getElementById('booking-form');
    const errorBanner  = document.getElementById('booking-error-banner');
    const successBanner= document.getElementById('booking-success-banner');
    const submitBtn    = bookingForm.querySelector('button[type="submit"]');
    const submitLabel  = submitBtn.querySelector('[data-submit-label]');
    const submitSpinner= submitBtn.querySelector('[data-submit-spinner]');

    const showError = msg => {
        errorBanner.innerHTML = `<strong>Booking failed:</strong> ${msg}`;
        errorBanner.classList.remove('d-none');
        successBanner.classList.add('d-none');
        errorBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    bookingForm.addEventListener('submit', async event => {
        event.preventDefault();

        // Client-side: check customer selected
        if (!idInput.value) {
            showError('Please select a customer before submitting.');
            return;
        }

        errorBanner.classList.add('d-none');
        successBanner.classList.add('d-none');
        submitBtn.disabled = true;
        submitLabel.classList.add('d-none');
        submitSpinner.classList.remove('d-none');

        try {
            // Build a JSON-friendly representation of the form for inspection
            const formDataToJSON = (fd) => {
                const obj = {};
                for (const [key, value] of fd.entries()) {
                    // handle nested keys like travellers[0][first_name]
                    const path = key.replace(/\]/g, '').split(/\[/);
                    let cur = obj;
                    for (let i = 0; i < path.length; i++) {
                        const p = path[i];
                        if (i === path.length - 1) {
                            // set value
                            if (cur[p] === undefined) cur[p] = value;
                            else if (Array.isArray(cur[p])) cur[p].push(value);
                            else cur[p] = [cur[p], value];
                        } else {
                            if (cur[p] === undefined) cur[p] = {};
                            cur = cur[p];
                        }
                    }
                }
                return obj;
            };

            const debugPayload = formDataToJSON(new FormData(bookingForm));
            console.log('Booking payload (client):', debugPayload);
            const response = await fetch(bookingForm.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: new FormData(bookingForm),
            });

            const body = await response.json();

            if (response.status === 422) {
                // Validation errors
                const messages = Object.values(body.errors || {}).flat();
                showError(messages.join('<br>') || body.message || 'Please check the form and try again.');
                return;
            }

            if (!response.ok) {
                throw new Error(body.message || `Server error (${response.status}).`);
            }

            // Success — show banner then redirect
            successBanner.textContent = `✓ ${body.message} Booking reference: ${body.reference}. Redirecting…`;
            successBanner.classList.remove('d-none');
            successBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });

            setTimeout(() => { window.location.href = body.redirect; }, 1800);

        } catch (err) {
            showError(err.message || 'An unexpected error occurred. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitLabel.classList.remove('d-none');
            submitSpinner.classList.add('d-none');
        }
    });
});
</script>@endpush

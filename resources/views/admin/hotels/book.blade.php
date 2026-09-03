@extends('layouts.admin')

@section('title', 'Complete hotel booking')

@section('content')
<header class="mb-4"><p class="text-danger fw-semibold mb-1">HOTEL OPERATIONS</p><h1 class="h3 fw-bold mb-2">Complete hotel booking</h1><p class="text-secondary mb-0">Review the stay, select the lead guest and prepare the final customer price.</p></header>

<form method="POST" action="{{ route('admin.hotels.orders.store', $offer) }}" class="admin-booking-form" id="hotel-booking-form">
    @csrf
    <div class="row g-4">
        <div class="col-xl-8">
            <section class="admin-panel-card">
                <div class="admin-panel-title">
                    <div><span>1</span><div><h2>Lead guest</h2><p>Search existing customers or create one without leaving this booking.</p></div></div>
                    <button class="btn btn-karossy btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addHotelCustomerModal"><i class="bi bi-person-plus"></i> Add customer</button>
                </div>
                <label class="form-label" for="hotel_customer_search">Select customer</label>
                <div class="booking-customer-picker" data-customer-picker>
                    <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" id="hotel_customer_search" type="search" placeholder="Search by name, email or phone" autocomplete="off" data-customer-search></div>
                    <input type="hidden" id="customer_id" name="customer_id" value="{{ old('customer_id') }}" required data-customer-id>
                    <div class="booking-customer-results" data-customer-results>
                        @forelse($customers as $customer)
                            <button type="button" class="booking-customer-option @if(old('customer_id')===$customer->id) selected @endif" data-customer-option data-id="{{ $customer->id }}" data-search="{{ strtolower($customer->full_name.' '.$customer->email.' '.$customer->phone) }}" data-name="{{ $customer->full_name }}" data-email="{{ $customer->email }}">
                                <span class="booking-customer-avatar">{{ strtoupper(substr($customer->first_name, 0, 1).substr($customer->last_name, 0, 1)) }}</span><span><strong>{{ $customer->full_name }}</strong><small>{{ $customer->email }}@if($customer->phone) · {{ $customer->phone }}@endif</small></span><i class="bi bi-check-circle-fill"></i>
                            </button>
                        @empty
                            <div class="booking-customer-empty">No customers yet. Use “Add customer” to create one.</div>
                        @endforelse
                    </div>
                    <div class="booking-selected-customer d-none" data-selected-customer><span><i class="bi bi-person-check"></i></span><div><small>Selected lead guest</small><strong data-selected-customer-name></strong><p data-selected-customer-email></p></div><button type="button" class="btn btn-link" data-change-customer>Change</button></div>
                </div>
                @error('customer_id')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </section>

            <section class="admin-panel-card mt-3">
                <div class="admin-panel-title"><div><span>2</span><div><h2>Stay details</h2><p>{{ $offer->search->check_in->format('D, d M Y') }} — {{ $offer->search->check_out->format('D, d M Y') }}</p></div></div></div>
                <div class="hotel-booking-property mb-4"><span><i class="bi bi-building"></i></span><div><small>{{ data_get($offer->location, 'city') }}, {{ data_get($offer->location, 'country') }}</small><h3>{{ $offer->name }}</h3><p class="mb-0">{{ $offer->room_name }} · {{ $offer->rate_name }}</p></div>@if($offer->rating)<b><i class="bi bi-star-fill"></i> {{ number_format($offer->rating, 1) }}</b>@endif</div>
                <div class="booking-detail-grid">
                    <div><small>Check-in</small><strong>{{ $offer->search->check_in->format('d M Y') }}</strong></div><div><small>Check-out</small><strong>{{ $offer->search->check_out->format('d M Y') }}</strong></div><div><small>Guests</small><strong>{{ $offer->search->adults }} adult(s), {{ $offer->search->children }} child(ren)</strong></div><div><small>Rooms</small><strong>{{ $offer->search->rooms }}</strong></div><div><small>Cancellation</small><strong>{{ $offer->refundable ? 'Refundable' : 'Non-refundable' }}</strong></div><div><small>Meal</small><strong>{{ $offer->breakfast_included ? 'Breakfast included' : 'Room only' }}</strong></div>
                </div>
            </section>

            <section class="admin-panel-card mt-3"><div class="admin-panel-title"><div><span>3</span><div><h2>Special requests</h2><p>Requests are subject to hotel availability.</p></div></div></div><textarea class="form-control" id="special_requests" name="special_requests" rows="4" maxlength="2000" placeholder="Late arrival, adjoining rooms, accessibility requirements…">{{ old('special_requests') }}</textarea></section>
        </div>

        <aside class="col-xl-4"><section class="admin-panel-card sticky-xl-top admin-order-summary" data-price-summary data-base-minor="{{ $offer->selling_total_minor }}">
            <span class="admin-eyebrow">STAY TOTAL</span><h2>{{ $offer->name }}</h2><p>{{ $offer->search->check_in->diffInDays($offer->search->check_out) }} night(s) · {{ $offer->search->rooms }} room(s)</p>
            <div class="admin-summary-line"><span>Configured customer rate</span><strong>{{ $offer->currency }} {{ number_format($offer->selling_total_minor / 100, 2) }}</strong></div>
            @if($addons->isNotEmpty())<h3 class="h6 mt-3">Optional add-ons</h3><div class="booking-addon-options">@foreach($addons as $addon)<label><input class="form-check-input" type="checkbox" name="addons[]" value="{{ $addon->id }}" data-addon-minor="{{ $addon->display_price_minor }}" @checked(in_array($addon->id, old('addons', []), true))><span><strong>{{ $addon->title }}</strong><small>{{ $addon->description ?: 'Optional hotel service' }}</small></span><b>{{ $offer->currency }} {{ number_format($addon->display_price_minor / 100, 2) }}</b></label>@endforeach</div>@endif
            <hr><h3 class="h6">Agent price adjustment</h3><p>Optional markup for this booking only.</p><div class="row g-2"><div class="col-6"><select class="form-select" name="operator_markup_type" data-markup-type><option value="none">No markup</option><option value="fixed" @selected(old('operator_markup_type')==='fixed')>Fixed</option><option value="percentage" @selected(old('operator_markup_type')==='percentage')>Percentage</option></select></div><div class="col-6"><input class="form-control" type="number" name="operator_markup_value" min="0" step="0.01" value="{{ old('operator_markup_value') }}" placeholder="Value" data-markup-value></div></div>
            <div class="admin-summary-line"><span>Customer total</span><strong data-customer-total>{{ $offer->currency }} {{ number_format($offer->selling_total_minor / 100, 2) }}</strong></div>
            <button class="btn btn-karossy w-100" type="submit"><i class="bi bi-building-check me-2"></i>Create hotel booking</button><p class="admin-no-payment"><i class="bi bi-info-circle"></i> Hotel bookings stay pending until confirmation is received.</p>
        </section></aside>
    </div>
</form>

<div class="modal fade" id="addHotelCustomerModal" tabindex="-1" aria-labelledby="addHotelCustomerTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content booking-customer-modal">
    <div class="modal-header"><div><span class="admin-eyebrow">NEW CUSTOMER</span><h2 class="modal-title" id="addHotelCustomerTitle">Add lead guest</h2><p>Create the customer and select them for this hotel booking.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <form data-add-booking-customer action="{{ route('admin.customers.store') }}" method="POST"><div class="modal-body"><div class="alert alert-danger d-none" data-customer-form-error></div><div class="row g-3">
        <div class="col-md-2"><label class="form-label">Title</label><select class="form-select" name="title"><option value="">—</option>@foreach(['Mr','Mrs','Ms','Miss','Dr','Prof'] as $title)<option>{{ $title }}</option>@endforeach</select></div><div class="col-md-5"><label class="form-label">First name</label><input class="form-control" name="first_name" required></div><div class="col-md-5"><label class="form-label">Last name</label><input class="form-control" name="last_name" required></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div><div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" type="tel" name="phone"></div><div class="col-md-4"><label class="form-label">Date of birth</label><input class="form-control" type="date" name="date_of_birth" max="{{ now()->subDay()->toDateString() }}"></div><div class="col-md-4"><label class="form-label">Gender</label><select class="form-select" name="gender"><option value="">Not specified</option><option value="male">Male</option><option value="female">Female</option><option value="unspecified">Prefer not to say</option></select></div><div class="col-md-4"><label class="form-label">Nationality code</label><input class="form-control text-uppercase" name="nationality" maxlength="2" placeholder="NG"></div><input type="hidden" name="status" value="active">
    </div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-karossy" type="submit"><span data-customer-submit-label>Create and select</span><span class="spinner-border spinner-border-sm d-none" data-customer-submit-spinner></span></button></div></form>
</div></div></div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const picker = document.querySelector('[data-customer-picker]');
    const search = picker?.querySelector('[data-customer-search]');
    const idInput = picker?.querySelector('[data-customer-id]');
    const results = picker?.querySelector('[data-customer-results]');
    const selected = picker?.querySelector('[data-selected-customer]');
    const choose = option => {
        idInput.value = option.dataset.id;
        selected.querySelector('[data-selected-customer-name]').textContent = option.dataset.name;
        selected.querySelector('[data-selected-customer-email]').textContent = option.dataset.email;
        results.classList.add('d-none'); search.closest('.input-group').classList.add('d-none'); selected.classList.remove('d-none');
    };
    results?.addEventListener('click', event => { const option = event.target.closest('[data-customer-option]'); if (option) choose(option); });
    search?.addEventListener('input', () => { const query = search.value.trim().toLowerCase(); results.querySelectorAll('[data-customer-option]').forEach(option => option.classList.toggle('d-none', query && !option.dataset.search.includes(query))); });
    picker?.querySelector('[data-change-customer]')?.addEventListener('click', () => { idInput.value = ''; selected.classList.add('d-none'); results.classList.remove('d-none'); search.closest('.input-group').classList.remove('d-none'); search.focus(); });
    const initial = idInput?.value ? results.querySelector(`[data-customer-option][data-id="${CSS.escape(idInput.value)}"]`) : null; if (initial) choose(initial);

    const addForm = document.querySelector('[data-add-booking-customer]');
    addForm?.addEventListener('submit', async event => {
        event.preventDefault(); const error = addForm.querySelector('[data-customer-form-error]'); const button = addForm.querySelector('button[type="submit"]'); error.classList.add('d-none'); button.disabled = true; addForm.querySelector('[data-customer-submit-spinner]').classList.remove('d-none');
        try {
            const response = await fetch(addForm.action, {method:'POST', headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body:new FormData(addForm)}); const body = await response.json();
            if (!response.ok) throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'Customer could not be created.');
            const customer = body.data; const option = document.createElement('button'); option.type='button'; option.className='booking-customer-option'; option.dataset.customerOption=''; option.dataset.id=customer.id; option.dataset.search=`${customer.name} ${customer.email} ${customer.phone || ''}`.toLowerCase(); option.dataset.name=customer.name; option.dataset.email=customer.email; option.innerHTML='<span class="booking-customer-avatar"></span><span><strong></strong><small></small></span><i class="bi bi-check-circle-fill"></i>'; option.querySelector('.booking-customer-avatar').textContent=customer.name.split(/\s+/).map(part=>part[0]).slice(0,2).join('').toUpperCase(); option.querySelector('strong').textContent=customer.name; option.querySelector('small').textContent=customer.email+(customer.phone?` · ${customer.phone}`:''); results.prepend(option); choose(option); addForm.reset(); bootstrap.Modal.getOrCreateInstance(document.getElementById('addHotelCustomerModal')).hide();
        } catch (exception) { error.textContent=exception.message; error.classList.remove('d-none'); }
        finally { button.disabled=false; addForm.querySelector('[data-customer-submit-spinner]').classList.add('d-none'); }
    });

    const box = document.querySelector('[data-price-summary]'); const total = box?.querySelector('[data-customer-total]'); const type = box?.querySelector('[data-markup-type]'); const value = box?.querySelector('[data-markup-value]');
    const calculate = () => { const base=Number(box.dataset.baseMinor||0), addons=[...box.querySelectorAll('[data-addon-minor]:checked')].reduce((sum,item)=>sum+Number(item.dataset.addonMinor||0),0), entered=Math.max(0,Number(value.value||0)), adjustment=type.value==='fixed'?Math.round(entered*100):(type.value==='percentage'?Math.round((base+addons)*entered/100):0); value.disabled=type.value==='none'; total.textContent='{{ $offer->currency }} '+((base+addons+adjustment)/100).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); };
    box?.addEventListener('input', calculate); box?.addEventListener('change', calculate); if (box) calculate();
});
</script>
@endpush

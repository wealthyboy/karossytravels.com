@php($editingOffer = $flightOffer ?? null)
<div class="card content-card p-4">
    <div class="row g-4">
        <div class="col-md-2"><label class="form-label fw-semibold">From airport</label><input class="form-control text-uppercase" name="origin_airport" maxlength="3" value="{{ old('origin_airport', $editingOffer?->origin_airport) }}" placeholder="LOS" required></div>
        <div class="col-md-4"><label class="form-label fw-semibold">From city</label><input class="form-control" name="origin_city" value="{{ old('origin_city', $editingOffer?->origin_city) }}" placeholder="Lagos" required></div>
        <div class="col-md-2"><label class="form-label fw-semibold">To airport</label><input class="form-control text-uppercase" name="destination_airport" maxlength="3" value="{{ old('destination_airport', $editingOffer?->destination_airport) }}" placeholder="LHR" required></div>
        <div class="col-md-4"><label class="form-label fw-semibold">To city</label><input class="form-control" name="destination_city" value="{{ old('destination_city', $editingOffer?->destination_city) }}" placeholder="London" required></div>

        <div class="col-md-4"><label class="form-label fw-semibold">Airline</label><input class="form-control" name="airline_name" value="{{ old('airline_name', $editingOffer?->airline_name) }}" placeholder="British Airways" required></div>
        <div class="col-md-2"><label class="form-label fw-semibold">Airline code</label><input class="form-control text-uppercase" name="airline_code" maxlength="3" value="{{ old('airline_code', $editingOffer?->airline_code) }}" placeholder="BA"></div>
        <div class="col-md-3"><label class="form-label fw-semibold">Departure date</label><input class="form-control" type="date" name="departure_date" value="{{ old('departure_date', $editingOffer?->departure_date?->toDateString()) }}" required></div>
        <div class="col-md-3"><label class="form-label fw-semibold">Return date</label><input class="form-control" type="date" name="return_date" value="{{ old('return_date', $editingOffer?->return_date?->toDateString()) }}" required></div>

        <div class="col-md-3"><label class="form-label fw-semibold">Cabin</label><select class="form-select" name="cabin">@foreach(['economy' => 'Economy', 'premium_economy' => 'Premium economy', 'business' => 'Business', 'first' => 'First'] as $value => $label)<option value="{{ $value }}" @selected(old('cabin', $editingOffer?->cabin ?? 'economy') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label fw-semibold">Currency</label><input class="form-control text-uppercase" name="currency" maxlength="3" value="{{ old('currency', $editingOffer?->currency ?? 'USD') }}" required></div>
        <div class="col-md-3"><label class="form-label fw-semibold">Starting price</label><input class="form-control" type="number" step="0.01" min="0" name="price" value="{{ old('price', $editingOffer ? number_format($editingOffer->price_minor / 100, 2, '.', '') : '') }}" required></div>
        <div class="col-md-2"><label class="form-label fw-semibold">Display order</label><input class="form-control" type="number" min="0" name="sort_order" value="{{ old('sort_order', $editingOffer?->sort_order ?? 0) }}"></div>
        <div class="col-md-2"><label class="form-label fw-semibold">Badge</label><input class="form-control" name="label" value="{{ old('label', $editingOffer?->label) }}" placeholder="Fresh deal"></div>

        <div class="col-md-6"><label class="form-label fw-semibold">Destination image URL</label><input class="form-control" type="url" name="image_url" value="{{ old('image_url', $editingOffer?->image_url) }}" placeholder="https://..."><small class="form-text">Useful for seeded or externally hosted destination photography.</small></div>
        <div class="col-md-6"><label class="form-label fw-semibold">Upload destination image</label><input class="form-control" type="file" accept="image/*" name="image"><small class="form-text">An uploaded image takes priority over the URL.</small></div>
        @if($editingOffer?->cover_url)<div class="col-12"><img src="{{ $editingOffer->cover_url }}" alt="Current offer image" class="rounded-3 object-fit-cover" style="width: 220px; height: 120px;"></div>@endif

        <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" @checked(old('active', $editingOffer?->active ?? true))><span class="form-check-label">Publish on the homepage</span></label></div>
    </div>
    <div class="alert alert-light border mt-4 mb-0"><i class="bi bi-info-circle me-2"></i>The displayed amount is a starting-price teaser. Clicking the offer always checks current airline inventory and pricing.</div>
    <div class="d-flex gap-2 justify-content-end mt-4"><a class="btn btn-outline-dark" href="{{ route('admin.flight-offers.index') }}">Cancel</a><button class="btn btn-karossy">Save flight offer</button></div>
</div>

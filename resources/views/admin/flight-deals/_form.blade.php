@php($editingDeal = $editingDeal ?? null)
<div class="card content-card p-4">
    <div class="row g-4 mb-4">
        <div class="col-md-6"><label class="form-label fw-semibold" for="deal-origin">Origin airport <span class="text-secondary">(optional)</span></label><input class="form-control text-uppercase" id="deal-origin" name="origin_airport" maxlength="3" value="{{ old('origin_airport', $editingDeal?->origin_airport) }}" placeholder="LOS"><small class="form-text">Leave blank to apply to every origin.</small></div>
        <div class="col-md-6"><label class="form-label fw-semibold" for="deal-destination">Destination airport <span class="text-secondary">(optional)</span></label><input class="form-control text-uppercase" id="deal-destination" name="destination_airport" maxlength="3" value="{{ old('destination_airport', $editingDeal?->destination_airport) }}" placeholder="LHR"><small class="form-text">Leave blank to apply to every destination.</small></div>
    </div>
    @if($editingDeal?->discount_type === 'fixed')<div class="alert alert-warning">This is a legacy fixed-amount deal. Saving it converts the deal to a percentage; enter the intended percentage below.</div>@endif
    @include('admin.flight-deals._promotion-fields', ['promotion' => $editingDeal])
    <label class="form-check mt-4"><input class="form-check-input" type="checkbox" name="active" value="1" @checked(old('active', $editingDeal?->active ?? true))><span class="form-check-label">Deal is active</span></label>
    <div class="alert alert-light border mt-4 mb-0"><i class="bi bi-info-circle me-2"></i>Applies once to the customer fare after normal pricing adjustments and before currency conversion. The route, validating airline and configured date windows must match.</div>
    <div class="d-flex gap-2 justify-content-end mt-4"><a class="btn btn-outline-dark" href="{{ route('admin.flight-deals.index') }}">Cancel</a><button class="btn btn-karossy">Save flight deal</button></div>
</div>

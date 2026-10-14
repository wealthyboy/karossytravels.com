@php
    $promotion = $promotion ?? null;
    $scope = old('airline_scope', $promotion?->all_airlines ? 'all' : 'selected');
    $selectedCodes = $promotion?->airline_codes ?? ($promotion?->airline ? [$promotion->airline->code] : []);
    $oldIds = old('airline_ids', $airlines->whereIn('code', $selectedCodes)->pluck('id')->all());
    $selectedIds = is_array($oldIds) ? array_map('strval', $oldIds) : [];
@endphp
<div class="row g-4" data-flight-promotion-fields>
    <div class="col-md-6">
        <label class="form-label fw-semibold" for="promotion-airline-scope">Discount applies to</label>
        <select class="form-select" name="airline_scope" id="promotion-airline-scope" data-promotion-scope>
            <option value="selected" @selected($scope === 'selected')>Selected airlines</option>
            <option value="all" @selected($scope === 'all')>All airlines</option>
        </select>
        <small class="form-text">This controls the live fares that receive the discount, not the airline shown on the homepage card.</small>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold" for="promotion-percent">Discount percentage</label>
        <div class="input-group"><input class="form-control" type="number" id="promotion-percent" name="discount_percent" min="0.01" max="99.99" step="0.01" value="{{ old('discount_percent', $promotion?->discount_type === 'percentage' ? $promotion->discount_value : '') }}" placeholder="10" required><span class="input-group-text">%</span></div>
        <small class="form-text">10 reduces each matching fare by 10%.</small>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold" for="promotion-priority">Priority</label>
        <input class="form-control" type="number" id="promotion-priority" name="priority" min="0" max="65535" value="{{ old('priority', $promotion?->priority ?? 0) }}">
        <small class="form-text">Highest priority wins. Discounts do not stack.</small>
    </div>
    <div class="col-12" data-promotion-airlines @if($scope === 'all') hidden @endif>
        <label class="form-label fw-semibold" for="promotion-airline-search">Choose discounted airlines</label>
        <input class="form-control mb-3" type="search" id="promotion-airline-search" placeholder="Search airline name or code" data-promotion-airline-search>
        <p class="small text-secondary" data-promotion-selected role="status" aria-live="polite">Selected: {{ $airlines->whereIn('id', $selectedIds)->pluck('name')->implode(', ') ?: 'None selected' }}</p>
        <div class="flight-promotion-airlines" role="group" aria-label="Discounted airlines">
            @foreach($airlines as $airline)
                <label class="form-check" data-promotion-airline-option>
                    <input class="form-check-input" type="checkbox" name="airline_ids[]" value="{{ $airline->id }}" @checked(in_array((string) $airline->id, $selectedIds, true))>
                    <span class="form-check-label">{{ $airline->name }} <small class="text-secondary">({{ $airline->code }})</small></span>
                </label>
            @endforeach
        </div>
        <small class="form-text">Choose one or more airlines, for example Qatar Airways and Turkish Airlines.</small>
    </div>
    <div class="col-12"><h3 class="h6 fw-bold mb-1">Promotion booking window <span class="text-secondary fw-normal">(optional)</span></h3><p class="small text-secondary mb-0">When customers can receive the discount. Both dates are inclusive, in Lagos time. Leave both blank if only departure dates matter.</p></div>
    <div class="col-md-6"><label class="form-label" for="promotion-booking-from">Promotion starts</label><input class="form-control" type="date" id="promotion-booking-from" name="booking_from" value="{{ old('booking_from', $promotion?->booking_from?->toDateString()) }}"></div>
    <div class="col-md-6"><label class="form-label" for="promotion-booking-until">Promotion ends</label><input class="form-control" type="date" id="promotion-booking-until" name="booking_until" value="{{ old('booking_until', $promotion?->booking_until?->toDateString()) }}"></div>
    <div class="col-12"><h3 class="h6 fw-bold mb-1">Eligible departure window <span class="text-secondary fw-normal">(optional)</span></h3><p class="small text-secondary mb-0">Flights departing within these dates qualify. The return date does not restrict the promotion. Set at least one complete date window; if both are set, both must match.</p></div>
    <div class="col-md-6"><label class="form-label" for="promotion-travel-from">Earliest departure</label><input class="form-control" type="date" id="promotion-travel-from" name="travel_from" value="{{ old('travel_from', $promotion?->travel_from?->toDateString()) }}"></div>
    <div class="col-md-6"><label class="form-label" for="promotion-travel-until">Latest departure</label><input class="form-control" type="date" id="promotion-travel-until" name="travel_until" value="{{ old('travel_until', $promotion?->travel_until?->toDateString()) }}"></div>
</div>

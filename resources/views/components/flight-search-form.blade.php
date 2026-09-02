@props(['departureDate', 'returnDate', 'action' => null, 'submitLabel' => 'Search flights', 'criteria' => [], 'showResults' => true])

<section class="flight-search-shell" data-flight-search>
    <div class="flight-trip-tabs" role="group" aria-label="Trip type">
        <input class="btn-check trip-type-input" type="radio" name="trip_type_ui" id="trip-round" value="round_trip" @checked(($criteria['trip_type'] ?? 'round_trip') === 'round_trip')>
        <label for="trip-round">Round trip</label>
        <input class="btn-check trip-type-input" type="radio" name="trip_type_ui" id="trip-one-way" value="one_way" @checked(($criteria['trip_type'] ?? null) === 'one_way')>
        <label for="trip-one-way">One-way</label>
        <input class="btn-check trip-type-input" type="radio" name="trip_type_ui" id="trip-multi-city" value="multi_city" @checked(($criteria['trip_type'] ?? null) === 'multi_city')>
        <label for="trip-multi-city">Multi-city</label>
    </div>

    <form class="flight-search-form" autocomplete="off" @if($action) action="{{ $action }}" method="GET" @endif>
        <input type="hidden" name="session_id">
        <input type="hidden" name="trip_type" value="{{ $criteria['trip_type'] ?? 'round_trip' }}" data-trip-type-value>
        <input type="hidden" name="currency" value="{{ app(\App\Travel\Pricing\DisplayCurrencyResolver::class)->resolve(request()) }}">
        <div class="flight-search-fields" data-standard-flight-fields>
            <div class="flight-location-pair">
                <label class="flight-search-control"><i class="bi bi-geo-alt-fill"></i><span><small>Leaving from</small><input data-airport-autocomplete value="{{ $criteria['origin'] ?? '' }}" placeholder="City or airport" aria-label="Origin airport" required><input type="hidden" name="origin" value="{{ $criteria['origin'] ?? '' }}" data-airport-code-input></span></label>
                <button type="button" class="flight-swap-button" aria-label="Swap origin and destination"><i class="bi bi-arrow-left-right"></i></button>
                <label class="flight-search-control"><i class="bi bi-geo-alt-fill"></i><span><small>Going to</small><input data-airport-autocomplete value="{{ $criteria['destination'] ?? '' }}" placeholder="City or airport" aria-label="Destination airport" required><input type="hidden" name="destination" value="{{ $criteria['destination'] ?? '' }}" data-airport-code-input></span></label>
            </div>
            <label class="flight-search-control flight-date-range-control">
                <i class="bi bi-calendar3"></i>
                <span>
                    <small data-flight-date-label>Departure — Return</small>
                    <input type="text" class="flight-date-range-input" data-flight-date-range aria-label="Travel dates" placeholder="Choose travel dates" readonly>
                    <input type="hidden" name="departure_date" value="{{ $criteria['departure_date'] ?? $departureDate }}" data-departure-date>
                    <input type="hidden" name="return_date" value="{{ $criteria['return_date'] ?? $returnDate }}" data-return-date>
                </span>
            </label>
            <div class="flight-traveller-picker" data-traveller-picker>
                <button class="flight-travellers-control" type="button" data-traveller-toggle aria-expanded="false"><i class="bi bi-person"></i><span><small>Travellers, Cabin class</small><strong data-traveller-summary>1 traveller, Economy</strong></span><i class="bi bi-chevron-down"></i></button>
                <div class="traveller-popover d-none" data-traveller-popover>
                    <h3>Travelers and Cabin class</h3>
                    @foreach([['adults','Adults',null,(int) ($criteria['adults'] ?? 1),9],['children','Children','Ages 2 to 17',(int) ($criteria['children'] ?? 0),8],['lap_infants','Infants on lap','Younger than 2',(int) ($criteria['infants'] ?? 0),4],['seat_infants','Infants in seat','Younger than 2',0,4]] as [$field,$label,$help,$value,$max])
                        <div class="traveller-counter" data-traveller-counter data-field="{{ $field }}" data-min="{{ $field === 'adults' ? 1 : 0 }}" data-max="{{ $max }}"><div><strong>{{ $label }}</strong>@if($help)<small>{{ $help }}</small>@endif</div><div><button type="button" data-counter-minus aria-label="Remove {{ strtolower($label) }}"><i class="bi bi-dash-lg"></i></button><span data-counter-value>{{ $value }}</span><button type="button" data-counter-plus aria-label="Add {{ strtolower($label) }}"><i class="bi bi-plus-lg"></i></button></div><input type="hidden" data-counter-input @if($field !== 'seat_infants') name="{{ $field === 'lap_infants' ? 'infants' : $field }}" @endif value="{{ $value }}"></div>
                    @endforeach
                    <label class="traveller-cabin"><span>Cabin class</span><select name="cabin" data-cabin-select>@foreach(['economy' => 'Economy', 'premium_economy' => 'Premium economy', 'business' => 'Business', 'first' => 'First'] as $value => $label)<option value="{{ $value }}" @selected(($criteria['cabin'] ?? 'economy') === $value)>{{ $label }}</option>@endforeach</select><i class="bi bi-chevron-down"></i></label>
                    <button class="btn btn-karossy traveller-done" type="button" data-traveller-done>Done</button>
                </div>
            </div>
        </div>
        <div class="multi-city-fields d-none" data-multi-city-fields>
            <div data-multi-city-segments></div>
            <button type="button" class="multi-city-add" data-add-flight-segment><i class="bi bi-plus-lg"></i> Add another flight</button>
        </div>
        <div class="flight-search-footer mt-2">
            <button class="btn btn-karossy flight-search-submit" type="submit"><span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span><span>{{ $submitLabel }}</span></button>
        </div>
    </form>
</section>

<template id="multi-city-segment-template">
    <div class="multi-city-segment" data-flight-segment>
        <div class="multi-city-segment-heading"><strong>Flight <span data-segment-number></span></strong><button type="button" data-remove-flight-segment aria-label="Remove flight"><i class="bi bi-x-lg"></i></button></div>
        <div class="multi-city-segment-fields">
            <div class="flight-location-pair">
                <label class="flight-search-control"><i class="bi bi-geo-alt-fill"></i><span><small>Leaving from</small><input data-segment-origin data-airport-autocomplete placeholder="City or airport" required><input type="hidden" data-segment-origin-code data-segment-airport-code></span></label>
                <button type="button" class="flight-swap-button" data-segment-swap aria-label="Swap origin and destination"><i class="bi bi-arrow-left-right"></i></button>
                <label class="flight-search-control"><i class="bi bi-geo-alt-fill"></i><span><small>Going to</small><input data-segment-destination data-airport-autocomplete placeholder="City or airport" required><input type="hidden" data-segment-destination-code data-segment-airport-code></span></label>
            </div>
            <label class="flight-search-control"><i class="bi bi-calendar3"></i><span><small>Date</small><input type="text" data-flight-segment-date placeholder="Choose date" readonly required></span></label>
        </div>
    </div>
</template>

@if($showResults)
<div class="flight-search-message d-none" role="alert"></div>
<section class="flight-results mt-4 d-none" aria-live="polite"><div class="d-flex justify-content-between align-items-end mb-3"><div><h2 class="h5 mb-1">Available flights</h2><p class="small text-secondary mb-0"><span data-result-count>0</span> offer(s) returned</p></div><small class="text-secondary">Prices include Karossy pricing rules</small></div><div class="flight-result-list"></div></section>
@endif

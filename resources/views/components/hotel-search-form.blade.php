@props(['checkIn', 'checkOut', 'action', 'submitLabel' => 'Search hotels', 'criteria' => []])

@php
    $destinationLabel = (string) data_get($criteria, 'destination_label', '');
    $destinationCode = (string) data_get($criteria, 'destination_code', '');
    $searchSession = (string) data_get($criteria, 'session_id', '');
@endphp

<form class="hotel-search-form" data-hotel-search autocomplete="off" action="{{ $action }}" method="GET">
    <input type="hidden" name="session_id" value="{{ $searchSession }}">
    <label class="hotel-search-control hotel-destination-control"><i class="bi bi-geo-alt-fill"></i><span><small>Where to?</small><input type="text" name="destination_label" data-hotel-destination placeholder="City or destination" value="{{ $destinationLabel }}" title="{{ $destinationLabel }}" required><input type="hidden" name="destination_code" data-hotel-destination-code value="{{ $destinationCode }}"></span></label>
    <label class="hotel-search-control hotel-date-control"><i class="bi bi-calendar3"></i><span><small>Dates</small><input type="text" data-hotel-date-range placeholder="Choose dates" readonly><input type="hidden" name="check_in" value="{{ $checkIn }}" data-hotel-check-in><input type="hidden" name="check_out" value="{{ $checkOut }}" data-hotel-check-out></span></label>
    <div class="hotel-guests-picker" data-hotel-guests>
        <button class="hotel-search-control hotel-guests-control" type="button" data-hotel-guests-toggle aria-expanded="false"><i class="bi bi-person"></i><span><small>Travellers</small><strong data-hotel-guests-summary>2 travellers, 1 room</strong></span><i class="bi bi-chevron-down"></i></button>
        <div class="hotel-guests-popover d-none" data-hotel-guests-popover><h3>Travellers and rooms</h3>
            @foreach([['adults','Adults',2,1,12],['children','Children',0,0,8],['rooms','Rooms',1,1,8]] as [$field,$label,$value,$min,$max])
                @php($currentValue = (int) data_get($criteria, $field, $value))
                <div class="traveller-counter" data-hotel-counter data-field="{{ $field }}" data-min="{{ $min }}" data-max="{{ $max }}"><div><strong>{{ $label }}</strong></div><div><button type="button" data-counter-minus aria-label="Remove {{ strtolower($label) }}"><i class="bi bi-dash-lg"></i></button><span data-counter-value>{{ $currentValue }}</span><button type="button" data-counter-plus aria-label="Add {{ strtolower($label) }}"><i class="bi bi-plus-lg"></i></button></div><input type="hidden" name="{{ $field }}" value="{{ $currentValue }}" data-counter-input></div>
            @endforeach
            <button class="btn btn-karossy traveller-done" type="button" data-hotel-guests-done>Done</button>
        </div>
    </div>
    <button class="btn btn-karossy hotel-search-submit" type="submit"><span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span><span>{{ $submitLabel }}</span></button>
    {{ $slot }}
</form>

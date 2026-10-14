@props(['checkIn', 'checkOut', 'action', 'submitLabel' => 'Search hotels', 'criteria' => []])

@php
    $destinationLabel = (string) data_get($criteria, 'destination_label', '');
    $destinationCode = (string) data_get($criteria, 'destination_code', '');
    $searchSession = (string) data_get($criteria, 'session_id', '');
@endphp

<form class="hotel-search-form" data-hotel-search autocomplete="off" action="{{ $action }}" method="GET" novalidate>
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
            <div class="hotel-child-ages" data-hotel-child-ages @if((int) data_get($criteria, 'children', 0) === 0) hidden @endif>
                <p>Select each child’s age at check-in.</p>
                <div class="hotel-child-ages-list" data-hotel-child-ages-list>
                    @for($child = 0; $child < (int) data_get($criteria, 'children', 0); $child++)
                        <label>Child {{ $child + 1 }} age<select class="form-select" name="children_ages[]" required aria-label="Child {{ $child + 1 }} age"><option value="">Select age</option>@foreach(range(0,17) as $age)<option value="{{ $age }}" @selected((string) data_get($criteria, 'children_ages.'.$child, '') === (string) $age)>{{ $age === 0 ? 'Under 1' : $age }}</option>@endforeach</select></label>
                    @endfor
                </div>
            </div>
            <p class="text-danger small" data-hotel-occupancy-error role="alert" hidden></p>
            <p class="text-danger small" data-hotel-age-error role="alert" hidden>Please select an age for every child.</p>
            <button class="btn btn-karossy traveller-done" type="button" data-hotel-guests-done>Done</button>
        </div>
    </div>
    <button class="btn btn-karossy hotel-search-submit" type="submit"><span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span><span>{{ $submitLabel }}</span></button>
    {{ $slot }}
</form>

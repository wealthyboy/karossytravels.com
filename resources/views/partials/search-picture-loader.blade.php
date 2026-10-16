@php
    $hotel = $type === 'hotel';
    $modalId = $hotel ? 'publicHotelSearchModal' : 'publicFlightSearchModal';
    $titleId = $modalId.'Title';
    $statusId = $hotel ? 'publicHotelSearchStatus' : 'publicFlightSearchStatus';
    $noteId = $hotel ? 'publicHotelSearchModalNote' : 'publicFlightSearchModalNote';
@endphp
<div class="modal fade {{ $hotel ? 'public-hotel-search-modal' : 'public-flight-search-modal' }}" data-public-picture-loader id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $titleId }}" aria-describedby="{{ $hotel ? $noteId : $statusId }}" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body public-flight-search-promo">
                <div class="public-flight-search-promo-media">
                    <img src="{{ asset('images/gyro-air/aircraft.jpg') }}" alt="Gyro Air private jet parked on the apron" width="2364" height="1578" decoding="async">
                    <div class="public-flight-search-promo-shade" aria-hidden="true"></div>
                    <span class="public-flight-search-sponsored">Karossy charter partner</span>
                    <div class="public-flight-search-promo-brand"><strong>GYRO AIR</strong><small>Private flights, planned around you.</small></div>
                    <div class="public-flight-loader-image-progress">
                        <div class="public-flight-loader-progress-meta"><span><i aria-hidden="true"></i><strong data-public-search-phase>Search in progress</strong></span><small>{{ $hotel ? 'Live rooms & availability' : 'Live fares & availability' }}</small></div>
                        <div class="public-search-progress public-flight-loader-progress" role="progressbar" aria-label="{{ $hotel ? 'Hotel' : 'Flight' }} search in progress" aria-valuetext="{{ $hotel ? 'Searching live hotel availability' : 'Searching live airline availability' }}" @if($hotel) data-public-hotel-progress @else data-public-flight-progress @endif><span></span></div>
                    </div>
                </div>
                <div class="public-flight-search-promo-copy">
                    <div class="public-flight-loader-heading">
                        <div><span class="modal-eyebrow">Live {{ $hotel ? 'hotel' : 'flight' }} search</span><h2 id="{{ $titleId }}">{{ $hotel ? 'Finding the right stays' : 'Finding your best flights' }}</h2></div>
                        <span class="public-flight-loader-route" @unless($hotel) data-public-search-route @endunless>{{ $context }}</span>
                    </div>
                    <p id="{{ $statusId }}" data-public-picture-status @if($hotel) data-public-hotel-search-status @else data-public-search-status @endif role="status" aria-live="polite" aria-atomic="true">{{ $hotel ? 'Checking rooms and live rates in '.$context.'…' : 'Checking live fares from '.$origin.' to '.$destination.'…' }}</p>
                    <div class="public-flight-loader-note"><i class="bi bi-info-circle" aria-hidden="true"></i><span id="{{ $noteId }}">Please keep this page open. Your {{ $hotel ? 'hotel' : 'flight' }} options will appear when the search finishes.</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

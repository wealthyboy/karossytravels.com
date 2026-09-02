@extends('layouts.public')

@section('title', $offer->name.' rooms')

@section('content')
@php
    $money = fn (int $minor, string $code) => \App\Support\CurrencyMetadata::format($minor, $code);
    $addressParts = array_filter([
        data_get($offer->location, 'address'),
        data_get($offer->location, 'city'),
        data_get($offer->location, 'country'),
    ]);
    $propertyAddress = implode(', ', $addressParts);
    $amenities = collect($offer->amenities ?? [])->filter()->unique()->values();
    $ratingLabel = $offer->rating >= 4.5 ? 'Exceptional' : ($offer->rating >= 4 ? 'Excellent' : ($offer->rating ? 'Guest favourite' : 'Karossy selected'));
    $hotelImageSeed = $offer->id.$offer->name;
    $hotelImage = fn (int $offset = 0) => asset(\App\Support\HotelImageDefaults::path($hotelImageSeed, $offset));
@endphp

<section class="hotel-detail-page">
    <div class="container public-container">
        <div class="hotel-detail-toolbar">
            <a class="hotel-rooms-back" href="{{ url()->previous() }}"><i class="bi bi-arrow-left"></i> Back to hotel results</a>
            <div><a href="#hotel-rooms"><i class="bi bi-calendar-check"></i> View rooms</a><button type="button" onclick='navigator.share && navigator.share({title: @js($offer->name), url: location.href})'><i class="bi bi-share"></i> Share</button></div>
        </div>

        <div class="hotel-detail-gallery" aria-label="Property gallery">
            <div class="hotel-gallery-main"><img src="{{ $hotelImage() }}" alt="{{ $offer->name }}"><span><strong>{{ $offer->name }}</strong><small>Karossy selected property view</small></span></div>
            <div class="hotel-gallery-tile gallery-lobby"><img src="{{ $hotelImage(1) }}" alt="Hotel lobby"><span>Lobby</span></div>
            <div class="hotel-gallery-tile gallery-room"><img src="{{ $hotelImage(2) }}" alt="Hotel room"><span>Rooms</span></div>
            <div class="hotel-gallery-tile gallery-dining"><img src="{{ $hotelImage(3) }}" alt="Hotel dining"><span>Dining</span></div>
            <div class="hotel-gallery-tile gallery-location"><img src="{{ $hotelImage(4) }}" alt="Hotel surroundings"><span>{{ data_get($offer->location, 'city') ?: 'Location' }}</span></div>
        </div>

        <div class="hotel-detail-overview">
            <main>
                <span class="public-eyebrow">Karossy verified stay</span>
                <div class="hotel-detail-title-row">
                    <div><h1>{{ $offer->name }}</h1><p><i class="bi bi-geo-alt-fill"></i> {{ $propertyAddress ?: 'Location details available with your reservation' }}</p></div>
                    @if($offer->rating)<div class="hotel-detail-score"><span><strong>{{ number_format($offer->rating, 1) }}</strong><small>/ 5</small></span><div><b>{{ $ratingLabel }}</b><small>Property rating</small></div></div>@endif
                </div>

                <section class="hotel-detail-highlights" aria-labelledby="hotel-highlights-title">
                    <h2 id="hotel-highlights-title">Highlights for your stay</h2>
                    <div>
                        <span><i class="bi bi-patch-check"></i><b>Live availability</b><small>Rates checked for your selected dates</small></span>
                        <span><i class="bi bi-shield-check"></i><b>Clear conditions</b><small>Refundability shown before selection</small></span>
                        <span><i class="bi bi-headset"></i><b>Travel support</b><small>Help from search through arrival</small></span>
                        @if($offer->breakfast_included)<span><i class="bi bi-cup-hot"></i><b>Breakfast available</b><small>Included with selected room rates</small></span>@endif
                    </div>
                </section>

                @if($amenities->isNotEmpty())
                <section class="hotel-detail-amenities" aria-labelledby="hotel-amenities-title">
                    <h2 id="hotel-amenities-title">Popular amenities</h2>
                    <div>@foreach($amenities as $amenity)<span><i class="bi bi-check2-circle"></i>{{ $amenity }}</span>@endforeach</div>
                </section>
                @endif
            </main>

            <aside class="hotel-detail-location-card">
                <div class="hotel-location-map"><i class="bi bi-geo-alt-fill"></i><span>Explore the area</span></div>
                <h2>{{ data_get($offer->location, 'city') ?: 'Property location' }}</h2>
                <p>{{ $propertyAddress ?: 'Full location details are available for this property.' }}</p>
                @if(data_get($offer->location, 'distance'))<span><i class="bi bi-signpost-2"></i>{{ data_get($offer->location, 'distance') }} {{ data_get($offer->location, 'distance_unit') }} from the search area</span>@endif
            </aside>
        </div>

        <div class="hotel-stay-summary">
            <span><i class="bi bi-calendar3"></i><small>Check-in</small><strong>{{ $offer->search->check_in->format('D, M j, Y') }}</strong></span>
            <span><i class="bi bi-calendar-check"></i><small>Check-out</small><strong>{{ $offer->search->check_out->format('D, M j, Y') }}</strong></span>
            <span><i class="bi bi-moon-stars"></i><small>Length of stay</small><strong>{{ $nights }} {{ str('night')->plural($nights) }}</strong></span>
            <span><i class="bi bi-people"></i><small>Guests and rooms</small><strong>{{ $offer->search->adults + $offer->search->children }} travellers · {{ $offer->search->rooms }} {{ str('room')->plural($offer->search->rooms) }}</strong></span>
            <a href="{{ url()->previous() }}"><i class="bi bi-pencil"></i> Change</a>
        </div>
    </div>
</section>

<section class="hotel-room-options hotel-detail-room-options" id="hotel-rooms">
    <div class="container public-container">
        <div class="results-heading">
            <div><span class="public-eyebrow">Choose your room</span><h2>Rooms and rates</h2><p>{{ $rooms->count() }} live {{ str('option')->plural($rooms->count()) }} for your selected dates.</p></div>
            <span class="hotel-room-currency"><i class="bi bi-info-circle"></i> Prices shown in {{ $currency }}</span>
        </div>
        <div class="hotel-room-list hotel-room-card-grid">
            @foreach($rooms as $index => $room)
                <article class="hotel-room-card hotel-room-card-premium">
                    <div class="hotel-room-visual"><img src="{{ $hotelImage($index + 5) }}" alt="{{ $room['offer']->room_name ?: 'Available hotel room' }}" loading="lazy"><span>{{ $room['offer']->room_name ?: 'Available room' }}</span></div>
                    <div class="hotel-room-copy"><div><h3>{{ $room['offer']->room_name ?: 'Available room' }}</h3><p>{{ $room['offer']->rate_name ?: 'Best available rate' }}</p><div class="hotel-room-benefits"><span class="positive"><i class="bi bi-check2"></i> Live rate</span>@if($room['offer']->breakfast_included)<span class="positive"><i class="bi bi-cup-hot"></i> Breakfast included</span>@else<span><i class="bi bi-cup-hot"></i> Room only</span>@endif<span class="{{ $room['offer']->refundable ? 'positive' : '' }}"><i class="bi {{ $room['offer']->refundable ? 'bi-check-circle' : 'bi-info-circle' }}"></i> {{ $room['offer']->refundable ? 'Refundable' : 'Cancellation rules apply' }}</span></div></div></div>
                    <div class="hotel-room-price"><small>Price per night</small><strong>{{ $money($room['nightly_minor'], $room['currency']) }}</strong><span>{{ $money($room['total_minor'], $room['currency']) }} total</span><small>Taxes and fees included</small><a class="btn btn-karossy" href="{{ route('hotels.checkout', $room['offer']) }}">Reserve</a></div>
                </article>
            @endforeach
        </div>

        <div class="hotel-detail-information">
            <section><span><i class="bi bi-shield-check"></i></span><div><h2>Booking conditions</h2><p>Cancellation and amendment conditions differ by room rate. Confirm the displayed rate terms with our travel team before payment.</p></div></section>
            <section><span><i class="bi bi-clock-history"></i></span><div><h2>Check-in information</h2><p>The property may request identification and a payment card at check-in. Special requests remain subject to availability.</p></div></section>
            <section><span><i class="bi bi-headset"></i></span><div><h2>Need help choosing?</h2><p>Karossy can help compare room benefits, cancellation flexibility and the total cost for your stay.</p><a href="mailto:{{ config('travel.support.email') }}?subject={{ rawurlencode('Help choosing a room at '.$offer->name) }}">Ask our travel team <i class="bi bi-arrow-right"></i></a></div></section>
        </div>
    </div>
</section>
@endsection

@php
    $money = fn (int $minor, string $code) => \App\Support\CurrencyMetadata::format($minor, $code);
@endphp

<div class="row g-4 public-filter-results public-hotel-results-layout" data-public-filter-shell>
    <button class="mobile-public-filter-trigger" type="button" data-open-public-filters><i class="bi bi-sliders"></i><span>Filter</span></button>
    <button class="mobile-public-filter-overlay" type="button" aria-label="Close filters" data-close-public-filters></button>
    <aside class="col-lg-3 public-filter-sidebar">
        <div class="results-filter-card public-filter-panel hotel-filter-panel">
            <div class="mobile-public-filter-header"><strong>Filter hotels</strong><button type="button" aria-label="Close filters" data-close-public-filters><i class="bi bi-x-lg"></i></button></div>
            <div class="hotel-filter-body">
                <div class="d-flex justify-content-between align-items-center"><h2>Filter by</h2><button type="button" data-clear-hotel-filters>Clear</button></div>
                <div class="filter-group"><strong>Guest rating</strong><label><input class="form-check-input" name="hotel_rating" value="4" type="radio"> 4 stars and above</label><label><input class="form-check-input" name="hotel_rating" value="3" type="radio"> 3 stars and above</label></div>
                <div class="filter-group"><strong>Popular amenities</strong><label><input class="form-check-input" name="hotel_breakfast" type="checkbox"> Breakfast included</label><label><input class="form-check-input" name="hotel_refundable" type="checkbox"> Refundable</label></div>
                <div class="filter-group"><strong>Display currency</strong><small>{{ $currency }} selected for your location</small></div>
            </div>
        </div>
    </aside>
    <div class="col-lg-9 public-hotel-results-main">
        <div class="hotel-offer-list">
            @forelse($properties as $property)
                @php
                    $offer = $property['offer'];
                    $hotelImageSeed = ($offer['id'] ?? '').($offer['name'] ?? 'hotel');
                    $hotelImages = collect(range(0, 3))->map(fn (int $offset) => asset(\App\Support\HotelImageDefaults::path($hotelImageSeed, $offset)));
                @endphp
                <article class="hotel-offer-card hotel-result-enter" data-hotel-result-card data-rating="{{ (float) ($offer['rating'] ?? 0) }}" data-breakfast="{{ $offer['breakfast_included'] ? '1' : '0' }}" data-refundable="{{ $offer['refundable'] ? '1' : '0' }}" data-price="{{ (int) $offer['price']['nightly_minor'] }}">
                    <div class="hotel-offer-image" data-hotel-image-slider>
                        <div class="hotel-offer-image-track">@foreach($hotelImages as $imageIndex => $hotelImage)<img src="{{ $hotelImage }}" alt="{{ $offer['name'] }} view {{ $imageIndex + 1 }}" loading="lazy" data-hotel-slide @class(['active' => $loop->first])>@endforeach</div>
                        <div class="hotel-image-dots" aria-label="Hotel images">@foreach($hotelImages as $imageIndex => $hotelImage)<button type="button" @class(['active' => $loop->first]) data-hotel-image-dot data-hotel-image-index="{{ $imageIndex }}" aria-label="Show image {{ $imageIndex + 1 }}"></button>@endforeach</div>
                    </div>
                    <div class="hotel-offer-content">
                        <div class="hotel-offer-copy">
                            <div class="hotel-rating">@if($offer['rating'])<strong>{{ number_format($offer['rating'], 1) }}</strong><span>Property rating</span>@endif</div>
                            <h2><a class="hotel-title-link" href="{{ route('hotels.rooms', $offer['id']) }}" aria-label="View rooms at {{ $offer['name'] }}">{{ $offer['name'] }} <i class="bi bi-chevron-right" aria-hidden="true"></i></a></h2>
                            <p class="hotel-location"><i class="bi bi-geo-alt"></i> {{ data_get($offer, 'location.address') }}, {{ data_get($offer, 'location.city') }}</p>
                            <div class="hotel-amenities">@foreach(array_slice($offer['amenities'], 0, 4) as $amenity)<span><i class="bi bi-check2"></i>{{ $amenity }}</span>@endforeach</div>
                            <div class="hotel-rate-details">
                                <strong>{{ $property['rates']->count() }} {{ str('rate')->plural($property['rates']->count()) }} available</strong>
                                <span>Starting with {{ $offer['room_name'] ?: 'an available room' }}</span>
                                @if($offer['breakfast_included'])<span class="text-success"><i class="bi bi-cup-hot"></i> Breakfast included</span>@endif
                                <span class="{{ $offer['refundable'] ? 'text-success' : 'text-secondary' }}"><i class="bi {{ $offer['refundable'] ? 'bi-check-circle' : 'bi-info-circle' }}"></i> {{ $offer['refundable'] ? 'Refundable options available' : 'Cancellation rules apply' }}</span>
                            </div>
                        </div>
                        <div class="hotel-offer-price">
                            <small>From, per night</small>
                            <strong>{{ $money($offer['price']['nightly_minor'], $offer['price']['currency']) }}</strong>
                            <span>{{ $money($offer['price']['total_minor'], $offer['price']['currency']) }} total</span>
                            <small>Taxes and fees included</small>
                            <a class="btn btn-karossy" href="{{ route('hotels.rooms', $offer['id']) }}">View rooms</a>
                        </div>
                    </div>
                </article>
                @if($loop->iteration === min(2, $loop->count))
                    <aside class="hotel-results-inline-ad" data-hotel-mobile-ad aria-label="Jiro Air charter services">
                        <a href="mailto:{{ config('travel.support.email') }}?subject=Jiro%20Air%20charter%20flight%20request">
                            <img src="{{ asset('images/ads/jiro-air-charter-v1.png') }}" alt="Private jet at sunset" loading="lazy">
                            <span class="jiro-ad-shine"></span>
                            <div class="jiro-ad-copy">
                                <small><i></i> Private charter</small>
                                <strong>JIRO AIR</strong>
                                <h3>Your aircraft.<br>Your schedule.</h3>
                                <p>Private, corporate and group charter flights tailored around you.</p>
                                <b>Request a charter <i class="bi bi-arrow-up-right"></i></b>
                            </div>
                        </a>
                    </aside>
                @endif
            @empty
                <div class="booking-card text-center"><i class="bi bi-building fs-1 text-secondary"></i><h2 class="h5 mt-3">No hotels found</h2><p class="text-secondary">Try another destination or different dates.</p><button class="btn btn-karossy" type="button" data-bs-toggle="collapse" data-bs-target="#inlineHotelSearch" aria-expanded="false" aria-controls="inlineHotelSearch">Change search</button></div>
            @endforelse
        </div>
    </div>
    <aside class="flight-results-ad hotel-results-ad" aria-label="Jiro Air charter services">
        <a href="mailto:{{ config('travel.support.email') }}?subject=Jiro%20Air%20charter%20flight%20request">
            <img src="{{ asset('images/ads/jiro-air-charter-v1.png') }}" alt="Private jet at sunset" loading="lazy">
            <span class="jiro-ad-shine"></span>
            <div class="jiro-ad-copy">
                <small><i></i> Private charter</small>
                <strong>JIRO AIR</strong>
                <h3>Your aircraft.<br>Your schedule.</h3>
                <p>Private, corporate and group charter flights tailored around you.</p>
                <b>Request a charter <i class="bi bi-arrow-up-right"></i></b>
            </div>
        </a>
    </aside>
</div>

@extends('layouts.public')

@section('title', 'Search flights, hotels and holidays')

@section('content')
<section class="public-hero" id="travel-search">
    <div class="public-hero-background" aria-hidden="true"></div>
    <div class="public-hero-pixels" aria-hidden="true"></div>
    <div class="container public-container position-relative">
        <div class="public-hero-intro">
            <div class="public-hero-copy">
                <span class="public-eyebrow"><i class="bi bi-stars"></i> Travel made simple</span>
                <h1>Your world, <em>within reach.</em></h1>
                <p>Compare flights, stays and travel services with expert support from search to arrival.</p>
                <div class="public-hero-assurance" aria-label="Karossy service benefits">
                    <span><i class="bi bi-check2-circle"></i> Live travel options</span>
                    <span><i class="bi bi-headset"></i> Human support</span>
                    <span><i class="bi bi-shield-check"></i> Secure booking</span>
                </div>
            </div>
            <div class="public-hero-visual" aria-hidden="true">
                <span class="hero-orbit hero-orbit-one"></span>
                <span class="hero-orbit hero-orbit-two"></span>
                <i class="bi bi-airplane-fill hero-plane"></i>
                <div class="hero-route-card" data-global-route-card>
                    <small>YOUR NEXT JOURNEY</small>
                    <div><span><strong data-route-origin-code>LHR</strong><small data-route-origin-city>London</small></span><b><i class="bi bi-airplane"></i></b><span><strong data-route-destination-code>DXB</strong><small data-route-destination-city>Dubai</small></span></div>
                    <p><i class="bi bi-stars"></i> Planned around you</p>
                </div>
            </div>
        </div>

        <div class="public-search-card">
            <div class="public-service-tabs" role="tablist" aria-label="Travel services">
                @foreach ([
                    ['flights', 'bi-airplane-fill', 'Flights'],
                    ['hotels', 'bi-building-fill', 'Hotels'],
                    ['cars', 'bi-car-front-fill', 'Cars'],
                    ['visas', 'bi-passport-fill', 'Visas'],
                    ['charter', 'bi-airplane-engines-fill', 'Charter'],
                ] as [$key, $icon, $label])
                    <button class="public-service-tab {{ $key === 'flights' ? 'active' : '' }}" type="button" role="tab" data-public-service-tab="{{ $key }}" aria-selected="{{ $key === 'flights' ? 'true' : 'false' }}">
                        <span class="public-service-icon service-{{ $key }}"><i class="bi {{ $icon }}"></i></span><span>{{ $label }}</span>
                    </button>
                @endforeach
            </div>

            <div class="public-service-panel" data-public-service-panel="flights">
                <x-flight-search-form :departure-date="$departureDate" :return-date="$returnDate" :action="route('flights.results')" />
            </div>
            <div class="public-service-panel d-none" data-public-service-panel="hotels" hidden inert>
                <x-hotel-search-form :check-in="$hotelCheckIn" :check-out="$hotelCheckOut" :action="route('hotels.results')"><label class="hotel-bundle-option"><input class="form-check-input" type="checkbox" name="bundle_flight" value="1"><span>Add a flight to Bundle &amp; Save*</span></label></x-hotel-search-form>
            </div>
            <div class="public-service-panel d-none" data-public-service-panel="visas"><form action="{{ route('visas.results') }}" class="home-visa-search"><label><span>Passport country</span><select name="passport_country" required data-searchable-select data-search-placeholder="Search passport country"><option value="">Select passport country</option>@foreach($passportCountries as $country)<option>{{ $country }}</option>@endforeach</select></label><label><span>Destination</span><select name="destination" required data-searchable-select data-search-placeholder="Search destination"><option value="">Where are you travelling to?</option>@foreach($visaDestinations as $country)<option>{{ $country }}</option>@endforeach</select></label><label><span>Travellers</span><select name="travellers"><option>1</option>@foreach(range(2,10) as $count)<option>{{ $count }}</option>@endforeach</select></label><button class="btn btn-karossy">Check requirements</button></form></div>
            <div class="public-service-panel d-none" data-public-service-panel="cars"><div class="public-coming-search"><span><i class="bi bi-car-front"></i></span><div><strong>Drive with the Karossy network</strong><small>We are inviting professional drivers and vehicle owners to partner with us.</small></div><a class="btn btn-karossy" href="{{ route('cars.partners') }}">Join us</a></div></div>
            <div class="public-service-panel d-none" data-public-service-panel="charter"><div class="public-coming-search"><span><i class="bi bi-airplane-engines"></i></span><div><strong>Fly on your own schedule</strong><small>Request private, corporate and group charter options from our travel team.</small></div><a class="btn btn-karossy" href="mailto:{{ config('travel.support.email') }}?subject=Charter%20flight%20request">Request a charter</a></div></div>
        </div>
    </div>
</section>

<section class="public-trust-strip"><div class="container public-container"><div><i class="bi bi-shield-check"></i><span><strong>Book with confidence</strong><small>Expert travel support when you need it</small></span></div><div><i class="bi bi-tags"></i><span><strong>Clear pricing</strong><small>No surprises at checkout</small></span></div><div><i class="bi bi-headset"></i><span><strong>Human support</strong><small>Real help before and after booking</small></span></div><div class="iata-trust"><i class="bi bi-shield-check"></i><span><strong>IATA</strong><small>Certified travel agency</small></span></div></div></section>

@php
    $destinationMoods = [
        'beach' => [
            'label' => 'Beach',
            'destinations' => [
                ['Santorini', 'Greece', 'Romantic getaway', 'https://images.unsplash.com/photo-1570077188670-e3a8d69ac5ff?auto=format&fit=crop&w=900&q=80', 'JTR'],
                ['Zanzibar', 'Tanzania', 'Island rhythm', 'https://images.unsplash.com/photo-1540202404-a2f29016b523?auto=format&fit=crop&w=900&q=80', 'ZNZ'],
                ['Dubai', 'United Arab Emirates', 'City and sun', 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?auto=format&fit=crop&w=900&q=80', 'DXB'],
                ['Cape Town', 'South Africa', 'Coast and culture', 'https://images.unsplash.com/photo-1580060839134-75a5edca2e99?auto=format&fit=crop&w=900&q=80', 'CPT'],
            ],
        ],
        'culture' => [
            'label' => 'Culture',
            'destinations' => [
                ['Istanbul', 'Türkiye', 'Two continents, one city', 'https://images.unsplash.com/photo-1524231757912-21f4fe3a7200?auto=format&fit=crop&w=900&q=80', 'IST'],
                ['Marrakech', 'Morocco', 'Souks and stories', 'https://images.unsplash.com/photo-1597212618440-806262de4f6b?auto=format&fit=crop&w=900&q=80', 'RAK'],
                ['Rome', 'Italy', 'History at every turn', 'https://images.unsplash.com/photo-1552832230-c0197dd311b5?auto=format&fit=crop&w=900&q=80', 'FCO'],
                ['Kyoto', 'Japan', 'Temples and tradition', 'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?auto=format&fit=crop&w=900&q=80', 'ITM'],
            ],
        ],
        'family' => [
            'label' => 'Family',
            'destinations' => [
                ['London', 'United Kingdom', 'Big sights, easy days', 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?auto=format&fit=crop&w=900&q=80', 'LHR'],
                ['Singapore', 'Singapore', 'Family city adventure', 'https://images.unsplash.com/photo-1525625293386-3f8f99389edd?auto=format&fit=crop&w=900&q=80', 'SIN'],
                ['Dubai', 'United Arab Emirates', 'Fun for every age', 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?auto=format&fit=crop&w=900&q=80', 'DXB'],
                ['Barcelona', 'Spain', 'City, beach and play', 'https://images.unsplash.com/photo-1539037116277-4db20889f2d4?auto=format&fit=crop&w=900&q=80', 'BCN'],
            ],
        ],
        'wellness' => [
            'label' => 'Wellness & relaxation',
            'destinations' => [
                ['Bali', 'Indonesia', 'Reset in paradise', 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=900&q=80', 'DPS'],
                ['Maldives', 'Maldives', 'Barefoot luxury', 'https://images.unsplash.com/photo-1514282401047-d79a71a590e8?auto=format&fit=crop&w=900&q=80', 'MLE'],
                ['Phuket', 'Thailand', 'Slow days by the sea', 'https://images.unsplash.com/photo-1589394815804-964ed0be2b86?auto=format&fit=crop&w=900&q=80', 'HKT'],
                ['Mauritius', 'Mauritius', 'Island calm', 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=900&q=80', 'MRU'],
            ],
        ],
    ];
@endphp

<section class="home-destinations" data-destination-tabs>
    <div class="container public-container">
        <div class="section-heading">
            <span class="public-eyebrow">Explore more</span>
            <h2>Cool destinations, unforgettable stays</h2>
            <p>From city breaks to sun-drenched islands, start with a destination that matches your mood.</p>
        </div>

        <div class="destination-tabs" role="tablist" aria-label="Destination moods">
            @foreach($destinationMoods as $moodKey => $mood)
                <button
                    type="button"
                    id="destination-tab-{{ $moodKey }}"
                    class="{{ $loop->first ? 'active' : '' }}"
                    role="tab"
                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                    aria-controls="destination-panel-{{ $moodKey }}"
                    tabindex="{{ $loop->first ? '0' : '-1' }}"
                    data-destination-tab="{{ $moodKey }}"
                >{{ $mood['label'] }}</button>
            @endforeach
        </div>

        @foreach($destinationMoods as $moodKey => $mood)
            <div
                id="destination-panel-{{ $moodKey }}"
                class="destination-card-grid {{ $loop->first ? '' : 'd-none' }}"
                role="tabpanel"
                aria-labelledby="destination-tab-{{ $moodKey }}"
                data-destination-panel="{{ $moodKey }}"
                @if(!$loop->first) hidden @endif
            >
                @foreach($mood['destinations'] as $destination)
                    @php
                        $destinationLabel = $destination[0].', '.$destination[1];
                        $hotelDestinationUrl = route('hotels.results', [
                            'destination_code' => $destination[4],
                            'destination_label' => $destinationLabel,
                            'check_in' => $hotelCheckIn,
                            'check_out' => $hotelCheckOut,
                            'adults' => 2,
                            'children' => 0,
                            'rooms' => 1,
                            'session_id' => (string) \Illuminate\Support\Str::uuid(),
                        ]);
                    @endphp
                    <a
                        href="{{ $hotelDestinationUrl }}"
                        data-home-hotel-destination
                        data-destination-code="{{ $destination[4] }}"
                        data-destination-label="{{ $destinationLabel }}"
                        aria-label="Find hotels in {{ $destinationLabel }}"
                    >
                        <img src="{{ $destination[3] }}" alt="{{ $destination[0] }}" loading="lazy">
                        <span>{{ $destination[2] }}</span>
                        <strong>{{ $destination[0] }}</strong>
                        <small>{{ $destination[1] }}</small>
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>
</section>

@if($flightOffers->isNotEmpty())
<section class="home-flight-offers home-flight-offers-featured" aria-labelledby="homepage-flight-offers-title">
    <div class="container public-container">
        <div class="section-heading split">
            <div>
                <span class="public-eyebrow">Live travel inspiration</span>
                <h2 id="homepage-flight-offers-title">Flight offers</h2>
                <p>Choose an offer to check current airline availability and today's live fare.</p>
            </div>
            <a href="{{ route('home', ['service' => 'flights']) }}#travel-search">Search another route <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="homepage-flight-offer-grid">
            @foreach($flightOffers as $offer)
                @php
                    $offerSearchUrl = route('flights.results', [
                        'origin' => $offer->origin_airport,
                        'destination' => $offer->destination_airport,
                        'departure_date' => $offer->departure_date->toDateString(),
                        'return_date' => $offer->return_date->toDateString(),
                        'trip_type' => 'round_trip',
                        'cabin' => $offer->cabin,
                        'adults' => 1,
                        'children' => 0,
                        'infants' => 0,
                        'session_id' => (string) \Illuminate\Support\Str::uuid(),
                    ]);
                @endphp
                <a class="homepage-flight-offer" href="{{ $offerSearchUrl }}" aria-label="Check live flights from {{ $offer->origin_city }} to {{ $offer->destination_city }}">
                    <span class="homepage-flight-offer-image">@if($offer->cover_url)<img src="{{ $offer->cover_url }}" alt="{{ $offer->destination_city }}">@else<i class="bi bi-airplane-fill"></i>@endif</span>
                    <span class="homepage-flight-offer-copy">
                        <span class="homepage-flight-offer-meta"><small>FLIGHTS</small><em>{{ $offer->label ?: 'FRESH FARE' }}</em></span>
                        <strong>{{ $offer->origin_city }} <i class="bi bi-arrow-right"></i> {{ $offer->destination_city }}</strong>
                        <span class="homepage-flight-offer-airports">{{ $offer->origin_airport }} → {{ $offer->destination_airport }}</span>
                        <span class="homepage-flight-offer-details"><b>{{ $offer->airline_name }}</b><i></i><b>{{ $offer->departure_date->format('M j') }} – {{ $offer->return_date->format('M j, Y') }}</b></span>
                        <span class="homepage-flight-offer-footer"><span><small>FROM</small><b>{{ \App\Support\CurrencyMetadata::format($offer->display_price['amount_minor'], $offer->display_price['currency'], 0) }}</b></span><em>View live flights <i class="bi bi-arrow-up-right"></i></em></span>
                    </span>
                </a>
            @endforeach
        </div>
        <p class="homepage-flight-offer-note"><i class="bi bi-info-circle"></i> Displayed prices are starting estimates. Current fare, seats, taxes and conditions are confirmed when you search.</p>
    </div>
</section>
@endif

<section class="home-charter-section" id="charter-flights">
    <div class="container public-container">
        <div class="home-charter-copy">
            <div class="home-charter-partner"><small><i></i> KAROSSY CHARTER PARTNER</small><strong>JIRO AIR</strong></div>
            <span class="public-eyebrow"><i class="bi bi-stars"></i> Private charter flights <b>Powered by Jiro Air</b></span>
            <h2>Your schedule. Your aircraft. Your journey.</h2>
            <p>Fly privately with Jiro Air for business, leisure, executive movements or group trips. Karossy manages your request, itinerary and travel support around your exact requirements.</p>
            <div class="home-charter-benefits">
                <span><i class="bi bi-clock-history"></i> Fly on your schedule</span>
                <span><i class="bi bi-people"></i> Private, corporate &amp; group travel</span>
                <span><i class="bi bi-headset"></i> Dedicated travel specialist</span>
            </div>
            <div class="home-charter-actions">
                <a class="btn btn-karossy" href="mailto:{{ config('travel.support.email') }}?subject=Charter%20flight%20request&amp;body=Hello%20Karossy%2C%0A%0AI%20would%20like%20to%20request%20a%20charter%20flight.%0A%0ADeparture%3A%0ADestination%3A%0ATravel%20date%3A%0ANumber%20of%20passengers%3A%0A%0AThank%20you.">Request a charter <i class="bi bi-arrow-up-right"></i></a>
                <a class="home-charter-contact" href="mailto:{{ config('travel.support.email') }}?subject=Speak%20to%20Karossy%20charter%20desk"><i class="bi bi-envelope"></i><span><small>Speak to our charter desk</small><strong>{{ config('travel.support.email') }}</strong></span></a>
            </div>
        </div>
    </div>
</section>

@if($holidayPackages->isNotEmpty())<section class="home-holidays"><div class="container public-container"><div class="section-heading split"><div><span class="public-eyebrow">Curated escapes</span><h2>Holidays planned around you</h2><p>Ready-made packages with room to personalise the details.</p></div><a href="{{ route('holidays.index') }}">View all packages <i class="bi bi-arrow-right"></i></a></div><div class="home-holiday-grid">@foreach($holidayPackages->take(3) as $package)<a href="{{ route('holidays.show',$package) }}"><img src="{{ $package->image_path?Storage::url($package->image_path):asset('images/holiday-hero-v2.png') }}" alt="{{ $package->destination }}"><span><small>{{ $package->country }}</small><strong>{{ $package->title }}</strong><b>From {{ \App\Support\CurrencyMetadata::format($package->display_price['amount_minor'], $package->display_price['currency'], 0) }}</b></span></a>@endforeach</div></div></section>@endif
<section class="home-app-section"><div class="container public-container"><div><span class="public-eyebrow">Karossy in your pocket</span><h2>Plan, book and manage your trip anywhere.</h2><p>Keep your flights, hotels, visa support and travel updates together in the Karossy mobile experience.</p><div class="app-store-actions"><span><i class="bi bi-apple"></i><small>Coming soon on</small><strong>App Store</strong></span><span><i class="bi bi-google-play"></i><small>Coming soon on</small><strong>Google Play</strong></span></div></div><div class="app-phone-preview"><span class="phone-logo"><img src="{{ asset('favicon.png') }}" alt=""> KAROSSY</span><small>YOUR NEXT TRIP</small><h3>Everything you need, ready when you are.</h3><div><i class="bi bi-airplane-fill"></i><span><strong>Lagos to London</strong><small>Trip details and updates in one place</small></span></div></div></div></section>
<section class="home-study-section" id="student-study-program">
    <div class="container public-container">
        <div class="home-study-visual" aria-hidden="true">
            <span class="home-study-globe"><i class="bi bi-globe2"></i></span>
            <span class="home-study-flight"><i class="bi bi-airplane-fill"></i></span>
            <div class="home-study-card home-study-card-one"><i class="bi bi-mortarboard-fill"></i><span><small>STUDY DESTINATION</small><strong>Your next chapter starts abroad</strong></span></div>
            <div class="home-study-card home-study-card-two"><i class="bi bi-passport-fill"></i><span><small>COMPLETE SUPPORT</small><strong>Application to arrival</strong></span></div>
        </div>
        <div class="home-study-copy">
            <span class="public-eyebrow"><i class="bi bi-mortarboard-fill"></i> Student Study Program</span>
            <h2>Study abroad with a team that plans every step.</h2>
            <p>From choosing the right destination to visa guidance and travel arrangements, Karossy supports students and families through the complete education journey.</p>
            <div class="home-study-points">
                <span><i class="bi bi-check2"></i> School and destination guidance</span>
                <span><i class="bi bi-check2"></i> Application and visa support</span>
                <span><i class="bi bi-check2"></i> Flights and pre-departure planning</span>
            </div>
            <a class="btn btn-karossy" href="{{ route('study-program') }}">Explore the study program <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
</section>
<section class="public-content-section home-about" id="about-karossy"><div class="container public-container"><div><span class="public-eyebrow">Travel made human</span><h2>More than a booking website.</h2></div><p>Karossy Travels combines live travel technology with the care of an experienced local team. Search confidently, understand what you are paying for, and get real support before, during and after every journey.</p></div></section>
@endsection

@extends('layouts.public')

@section('title', 'Book Flights, Hotels & Holiday Packages in Nigeria')
@section('meta_description', 'Book affordable domestic and international flights, hotels and holiday packages with Karossy Travels, a trusted travel agency in Nigeria.')

@section('content')
<section class="public-hero" id="travel-search">
    <div class="public-hero-background" aria-hidden="true"></div>
    <div class="public-hero-pixels" aria-hidden="true"></div>
    <div class="container public-container position-relative">
        <div class="public-hero-intro">
            <div class="public-hero-copy">
                <span class="public-eyebrow"><i class="bi bi-stars"></i> Travel made simple</span>
                <h1>Your world, <em>within reach.</em></h1>
                <p>From stress-free visa support to great flight deals and custom tour packages.</p>
                <div class="public-hero-assurance" aria-label="Karossy service benefits">
                    <span><i class="bi bi-check2-circle"></i> Live travel options</span>
                    <span><i class="bi bi-headset"></i> 24/7 Assistance</span>
                    <span><i class="bi bi-shield-check"></i> Secure booking</span>
                    <span><i class="bi bi-shield-check"></i> IATA certified travel agency</span>
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
            <div class="public-service-panel d-none" data-public-service-panel="visas">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div><strong>Planning to travel abroad?</strong><small class="d-block text-secondary">Tell our visa team about your trip for personalised guidance.</small></div>
                    <a class="btn btn-karossy" href="{{ route('visas.enquiry') }}">Submit a visa enquiry</a>
                </div>
            </div>
            <div class="public-service-panel d-none" data-public-service-panel="cars"><div class="public-coming-search"><span><i class="bi bi-car-front"></i></span><div><strong>Reliable transportation for your journey</strong><small>Airport transfers, chauffeur-driven cars, daily hire and group transportation.</small></div><div class="public-coming-actions"><a class="btn btn-karossy" href="{{ route('cars.hire') }}">Request Car Hire</a><a class="btn btn-outline-dark" href="{{ route('cars.partners') }}">Join our driver network</a></div></div></div>
            <div class="public-service-panel d-none" data-public-service-panel="charter"><div class="public-coming-search"><span><i class="bi bi-airplane-engines"></i></span><div><strong>Plan your private flight with Karossy</strong><small>Tell us where you need to go, when you want to travel and how many people are flying. Our charter team will arrange the aircraft, route and support around your plans.</small></div><div class="public-coming-actions"><a class="btn btn-karossy" href="tel:+2347061382134"><i class="bi bi-telephone"></i> Call +234 706 138 2134</a><a class="btn btn-outline-dark" href="mailto:{{ config('travel.support.email') }}?subject=Charter%20flight%20request"><i class="bi bi-envelope"></i> Request a charter</a></div></div></div>
        </div>
    </div>
</section>

{{--
<section class="public-trust-strip">
    <div class="container public-container">
        <div class="public-trust-card">
            <div><i class="bi bi-shield-check"></i><span><strong>Book with confidence</strong><small>Expert travel support when you need it</small></span></div>
            <div><i class="bi bi-tags"></i><span><strong>Clear pricing</strong><small>No surprises at checkout</small></span></div>
            <div><i class="bi bi-headset"></i><span><strong>24/7 Assistance</strong><small>Real help before and after booking</small></span></div>
            <div class="iata-trust"><i class="bi bi-shield-check"></i><span><strong>IATA</strong><small>Certified travel agency</small></span></div>
        </div>
    </div>
</section>
--}}

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

<section class="home-charter-section" id="charter-flights" aria-labelledby="home-charter-title">
    <div class="container public-container">
        <div class="home-charter-heading">
            <div class="home-charter-copy">
                <span class="public-eyebrow"><i class="bi bi-airplane-engines" aria-hidden="true"></i> Private charter flights</span>
                <h2 id="home-charter-title">Private flights, <em>your way.</em></h2>
                <p>Business, leisure or a group journey. Karossy coordinates your charter with Gyro Air, from your first enquiry to a carefully planned itinerary.</p>
            </div>
            <div class="home-charter-partner"><small>KAROSSY CHARTER PARTNER</small><strong>GYRO AIR</strong><span>Private aviation, together.</span></div>
        </div>
        <div class="home-charter-feature" data-gyro-gallery role="region" aria-roledescription="carousel" aria-label="Gyro Air photo gallery">
            <figure class="home-charter-photo" id="gyro-air-gallery-photo">
                <img class="is-active" src="{{ asset('images/gyro-air/aircraft.jpg') }}" alt="Gyro Air private jet parked on the apron" width="2364" height="1578" loading="lazy" decoding="async" data-gyro-gallery-slide aria-hidden="false">
                <img class="gyro-cockpit-slide" src="{{ asset('images/gyro-air/cockpit.jpg') }}" alt="Gyro Air pilots seated in the aircraft cockpit" width="1920" height="1276" loading="lazy" decoding="async" data-gyro-gallery-slide aria-hidden="true">
                <figcaption><span>PLANNED AROUND YOU</span><strong>Your schedule. Your aircraft. Your journey.</strong></figcaption>
            </figure>
            <div class="home-charter-gallery-nav">
                <div><strong>A closer look at Gyro Air</strong><span>Explore the aircraft and cockpit.</span><button class="home-charter-gallery-playback" type="button" data-gyro-gallery-playback aria-label="Pause Gyro Air slideshow" aria-pressed="false"><i class="bi bi-pause-fill" aria-hidden="true"></i><span data-gyro-gallery-playback-label>Pause slideshow</span></button></div>
                <div class="home-charter-gallery-choices" role="group" aria-label="Choose a Gyro Air photo">
                    <button type="button" aria-label="Show aircraft photo" aria-pressed="true" aria-controls="gyro-air-gallery-photo" data-gyro-gallery-select data-gyro-label="Aircraft">
                        <img src="{{ asset('images/gyro-air/aircraft.jpg') }}" alt="" width="90" height="60" loading="lazy"><span>Aircraft</span>
                    </button>
                    <button type="button" aria-label="Show cockpit photo" aria-pressed="false" aria-controls="gyro-air-gallery-photo" data-gyro-gallery-select data-gyro-label="Cockpit">
                        <img src="{{ asset('images/gyro-air/cockpit.jpg') }}" alt="" width="90" height="60" loading="lazy"><span>Cockpit</span>
                    </button>
                </div>
                <span class="visually-hidden" data-gyro-gallery-status role="status" aria-live="off">Photo 1 of 2: Aircraft</span>
            </div>
            <div class="home-charter-footer">
                <div class="home-charter-benefits" aria-label="Charter travel support">
                    <span><i class="bi bi-clock-history" aria-hidden="true"></i> Fly on your schedule</span>
                    <span><i class="bi bi-people" aria-hidden="true"></i> Private, corporate &amp; group travel</span>
                    <span><i class="bi bi-headset" aria-hidden="true"></i> Dedicated travel specialist</span>
                </div>
                <div class="home-charter-actions">
                    <a class="btn btn-karossy" href="tel:+2347061382134"><i class="bi bi-telephone" aria-hidden="true"></i> Call +234 706 138 2134</a>
                    <a class="home-charter-contact" href="mailto:{{ config('travel.support.email') }}?subject=Charter%20flight%20request&amp;body=Hello%20Karossy%2C%0A%0AI%20would%20like%20to%20request%20a%20charter%20flight.%0A%0ADeparture%3A%0ADestination%3A%0ATravel%20date%3A%0ANumber%20of%20passengers%3A%0A%0AThank%20you."><i class="bi bi-envelope" aria-hidden="true"></i><span>Email our charter desk</span><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

@if($holidayPackages->isNotEmpty())
    @php
        $holidayFallbackImages = [
            'explore-zanzibar' => asset('images/holidays/zanzibar.jpg'),
            'summer-in-egypt' => asset('images/holidays/egypt.jpg'),
            'magical-morocco' => asset('images/holidays/morocco.jpg'),
            'couples-escape-to-maldives' => asset('images/holidays/maldives.jpg'),
            'experience-singapore' => asset('images/holidays/singapore.jpg'),
        ];
    @endphp
    <section class="home-holidays">
        <div class="container public-container">
            <div class="section-heading split">
                <div><span class="public-eyebrow">Curated escapes</span><h2>Holidays planned around you</h2><p>Ready-made packages with room to personalise the details.</p></div>
                <a href="{{ route('holidays.index') }}">View all packages <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="home-holiday-grid">
                @foreach($holidayPackages->take(3) as $package)
                    @php($holidayImage = $package->image_path ? Storage::url($package->image_path) : ($holidayFallbackImages[$package->slug] ?? asset('images/holiday-hero-v2.jpg')))
                    <a href="{{ route('holidays.show',$package) }}">
                        <img src="{{ $holidayImage }}" alt="{{ $package->destination }}">
                        <span><small>{{ $package->country }}</small><strong>{{ $package->title }}</strong><b>From {{ \App\Support\CurrencyMetadata::format($package->display_price['amount_minor'], $package->display_price['currency'], 0) }}</b></span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
<section class="home-app-section"><div class="container public-container"><div><span class="public-eyebrow">The Karossy app</span><h2>Make every journey easier from your phone.</h2><p>Download the Karossy app to search, book and manage flights, hotels, visa support and travel updates wherever you go.</p><span class="visually-hidden">Plan, book and manage your trip anywhere.</span><div class="app-store-actions"><a href="https://apps.apple.com/" target="_blank" rel="noopener noreferrer" aria-label="Open the Apple App Store"><i class="bi bi-apple"></i><small><span class="app-store-copy-desktop">Get it on</span><span class="app-store-copy-mobile">Download on</span></small><strong>App Store</strong></a><a href="https://play.google.com/store/apps" target="_blank" rel="noopener noreferrer" aria-label="Open Google Play"><i class="bi bi-google-play"></i><small><span class="app-store-copy-desktop">Get it on</span><span class="app-store-copy-mobile">Download on</span></small><strong>Google Play</strong></a></div></div><div class="app-phone-preview"><span class="phone-logo"><img src="{{ asset('favicon.jpg') }}" alt=""> KAROSSY</span><small>YOUR NEXT TRIP</small><h3>Everything you need, ready when you are.</h3><div><i class="bi bi-airplane-fill"></i><span><strong>Lagos to London</strong><small>Trip details and updates in one place</small></span></div></div></div></section>
<section class="home-study-section" id="student-study-program" aria-labelledby="home-study-title">
    <div class="container public-container">
        <div class="home-study-feature">
            <div class="home-study-copy">
                <span class="home-study-eyebrow"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i> Student Study Program</span>
                <h2 id="home-study-title">Your next chapter.<br><em>Beyond borders.</em></h2>
                <p>From choosing the right school to getting ready for take-off, we help students and families plan their study-abroad journey with confidence.</p>
                <a class="btn btn-karossy" href="{{ route('study-program') }}">Explore the study program <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                <span class="home-study-note"><i class="bi bi-people" aria-hidden="true"></i> Personal guidance for students and families</span>
            </div>
            <div class="home-study-journey">
                <div class="home-study-journey-heading">
                    <div><span>THE KAROSSY STUDY JOURNEY</span><h3>Big ambitions. Clear next steps.</h3></div>
                    <i class="bi bi-mortarboard" aria-hidden="true"></i>
                </div>
                <ol class="home-study-steps">
                    <li><span class="home-study-step-number" aria-hidden="true">01</span><div><h4>Find your direction</h4><p>School and destination guidance, tailored to your plans.</p></div><i class="bi bi-compass" aria-hidden="true"></i></li>
                    <li><span class="home-study-step-number" aria-hidden="true">02</span><div><h4>Prepare your application</h4><p>Application and visa support, with the details in focus.</p></div><i class="bi bi-passport" aria-hidden="true"></i></li>
                    <li><span class="home-study-step-number" aria-hidden="true">03</span><div><h4>Get ready for take-off</h4><p>Flights and pre-departure planning for your next chapter.</p></div><i class="bi bi-airplane" aria-hidden="true"></i></li>
                </ol>
                <div class="home-study-journey-footer"><span><i class="bi bi-geo-alt" aria-hidden="true"></i> Application</span><span class="home-study-journey-line" aria-hidden="true"><i class="bi bi-airplane-fill"></i></span><span>Arrival <i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span></div>
            </div>
        </div>
    </div>
</section>
<section class="public-content-section home-about" id="about-karossy">
    <div class="container public-container">
        <div class="home-about-heading">
            <span class="public-eyebrow">About Karossy</span>
            <h2>Book Affordable Flights, Hotels and Holiday Packages with Karossy Travels.</h2>
        </div>
        <div class="home-about-copy">
            <p>Karossy Travels &amp; Tours Limited is a travel destination management company based in Lagos, Nigeria, with a presence across other parts of Nigeria and the United Kingdom. We provide comprehensive travel solutions designed to make every journey convenient, seamless and rewarding. Our approach is centred on understanding the unique needs of our clients and delivering personalized services with professionalism, care and attention to detail.</p>
            <p>We cater to individuals, families, corporate organizations and groups, offering a wide range of services that cover different aspects of travel. Our services include flight bookings, charter flight services, hotel reservations, customized holiday packages, pilgrimage, corporate travel management, visa assistance, study abroad programs, travel insurance, car hire, cargo services and travel logistics coordination. Whether it is a business trip, a family holiday, a group tour or a specialized travel arrangement, we work to provide solutions that align with our clients’ needs, preferences and objectives.</p>
            <p>At Karossy, we believe that effective travel management goes beyond bookings and reservations. It requires thoughtful planning, efficient coordination, clear communication and dependable support. We are committed to offering competitive pricing and value-driven travel solutions that balance affordability, quality and convenience. By understanding our clients’ budgets and individual requirements, we work to identify suitable options without losing sight of the comfort, experience and reliability they deserve. Through our corporate travel management services, we help businesses coordinate their travel requirements, while our leisure travel solutions are designed to create memorable experiences through carefully planned itineraries and tailored arrangements.</p>
            <p>Our guiding slogan, “Your Satisfaction, Our Peace,” reflects the principle behind everything we do. We believe that our success is closely tied to the satisfaction and confidence of those we serve. We therefore place great importance on listening to our clients, understanding their expectations, paying attention to the details and working diligently to deliver solutions that meet their needs. From the initial enquiry to the completion of travel arrangements, our goal is to provide a positive and reassuring service experience.</p>
            <p>As the travel industry continues to evolve, we remain committed to continuous improvement, innovation and building lasting relationships based on trust and consistent service delivery. Our ambition is to strengthen our position as a dependable travel partner, providing practical solutions that support business mobility, personal travel and meaningful experiences across destinations.</p>
            <p>At Karossy Travels &amp; Tours Limited, every journey is an opportunity to deliver value, build trust and exceed expectations. We are committed to making travel planning easier, experiences more rewarding and client satisfaction the standard by which we measure our service.</p>
            <p><strong>Karossy Travels &amp; Tours Limited - Your Satisfaction, Our Peace.</strong></p>
        </div>
    </div>
</section>
@endsection

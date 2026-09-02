@extends('layouts.public')

@section('title', 'Search flights, hotels and holidays')

@section('content')
<section class="public-hero" id="travel-search">
    <div class="public-hero-pixels" aria-hidden="true"></div>
    <div class="container public-container position-relative">
        <div class="public-hero-copy">
            <span class="public-eyebrow"><i class="bi bi-stars"></i> Travel made simple</span>
            <h1>Go further, for less.</h1>
            <p>Compare flights and plan every part of your journey in one trusted place.</p>
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
            <div class="public-service-panel d-none" data-public-service-panel="hotels">
                <x-hotel-search-form :check-in="$departureDate" :check-out="$returnDate" :action="route('hotels.results')"><label class="hotel-bundle-option"><input class="form-check-input" type="checkbox" name="bundle_flight" value="1"><span>Add a flight to Bundle &amp; Save*</span></label></x-hotel-search-form>
            </div>
            @foreach ([
                'cars' => ['bi-car-front', 'Move freely at your destination', 'Compare trusted car-hire partners and vehicle options.'],
                'visas' => ['bi-passport', 'Travel document support', 'Start a guided visa request with the Karossy travel team.'],
                'charter' => ['bi-airplane-engines', 'Fly on your own schedule', 'Request private and group charter flight options.'],
            ] as $key => [$icon, $title, $copy])
                <div class="public-service-panel d-none" data-public-service-panel="{{ $key }}">
                    <div class="public-coming-search"><span><i class="bi {{ $icon }}"></i></span><div><strong>{{ $title }}</strong><small>{{ $copy }}</small></div><button class="btn btn-karossy" type="button">Coming next</button></div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="public-trust-strip"><div class="container public-container"><div><i class="bi bi-shield-check"></i><span><strong>Book with confidence</strong><small>Expert travel support when you need it</small></span></div><div><i class="bi bi-tags"></i><span><strong>Clear pricing</strong><small>No surprises at checkout</small></span></div><div><i class="bi bi-headset"></i><span><strong>Human support</strong><small>Real help before and after booking</small></span></div><div class="iata-trust"><i class="bi bi-shield-check"></i><span><strong>IATA</strong><small>Certified travel agency</small></span></div></div></section>

<section class="public-content-section"><div class="container public-container"><span class="public-eyebrow">Explore with Karossy</span><h2>Everything your journey needs</h2><p>Flights are only the beginning. We are building one connected experience for hotels, cars, visas and charter services.</p></div></section>
@endsection

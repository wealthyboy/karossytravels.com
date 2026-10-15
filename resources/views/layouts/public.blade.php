<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $seoTitle = html_entity_decode(trim($__env->yieldContent('seo_title') ?: $__env->yieldContent('title', 'Flights, hotels and holidays').' | Karossy Travels'), ENT_QUOTES, 'UTF-8');
        $seoDescription = trim($__env->yieldContent('meta_description') ?: 'Book affordable flights, hotels, holiday packages, visa support and travel services with Karossy Travels in Nigeria.');
        $seoCanonical = $__env->yieldContent('canonical_url') ?: request()->url();
        $seoImage = $__env->yieldContent('seo_image') ?: asset('images/holiday-hero-v2.png');
    @endphp
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="@yield('robots', 'index,follow')">
    <link rel="canonical" href="{{ $seoCanonical }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="Karossy Travels">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoCanonical }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-site">
@php
    $displayCurrency = app(\App\Travel\Pricing\DisplayCurrencyResolver::class)->resolve(request());
    $supportedCurrencies = config('travel.currency.public_supported', ['NGN', 'USD', 'GBP', 'EUR']);
    $serviceUrl = fn (string $service) => route($service === 'flights' ? 'book-flights' : ($service === 'hotels' ? 'book-hotels' : 'home'), $service === 'flights' || $service === 'hotels' ? [] : ['service' => $service]).'#travel-search';
    $whatsappPhone = preg_replace('/\D+/', '', (string) config('travel.support.whatsapp'));
    if ($whatsappPhone !== '' && str_starts_with($whatsappPhone, '0')) {
        $whatsappPhone = '234'.substr($whatsappPhone, 1);
    }
    $whatsappUrl = $whatsappPhone !== ''
        ? 'https://wa.me/'.$whatsappPhone.'?text='.rawurlencode('Hello Karossy Travels, I need help planning my trip.')
        : null;
@endphp
<header class="public-header">
    <nav class="navbar" aria-label="Main navigation">
        <div class="container public-container">
            <a class="public-brand" href="{{ route('home') }}" aria-label="Karossy Travels home">
                <img class="public-brand-logo" src="{{ asset('karossy-logo-white.svg') }}" alt="Karossy Travels and Tours Limited" width="372" height="86">
            </a>
            <div class="public-primary-nav public-desktop-service-nav" aria-label="Travel products">
                <a class="nav-link {{ request()->routeIs('home', 'flights.*', 'checkout.*') && request('service', 'flights') === 'flights' ? 'active' : '' }}" href="{{ $serviceUrl('flights') }}" data-header-service="flights">Flights</a>
                <a class="nav-link {{ request()->routeIs('hotels.*') || request('service') === 'hotels' ? 'active' : '' }}" href="{{ $serviceUrl('hotels') }}" data-header-service="hotels">Hotels</a>
                <a class="nav-link {{ request()->routeIs('cars.*') ? 'active' : '' }}" href="{{ route('cars.hire') }}">Cars</a>
                <a class="nav-link {{ request()->routeIs('visas.*') ? 'active' : '' }}" href="{{ route('visas.enquiry') }}">Visa services</a>
                <a class="nav-link {{ request('service') === 'charter' ? 'active' : '' }}" href="{{ $serviceUrl('charter') }}" data-header-service="charter">Charter</a>
                <a class="nav-link {{ request()->routeIs('holidays.*') ? 'active' : '' }}" href="{{ route('holidays.index') }}">Holidays</a>
                <a class="nav-link {{ request()->routeIs('study-program') ? 'active' : '' }}" href="{{ route('study-program') }}">Student Study Program</a>
            </div>
            <div class="public-header-actions">
                @php($activeCurrencyMeta = \App\Support\CurrencyMetadata::for($displayCurrency))
                <div class="dropdown"><button class="public-header-action public-currency" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="public-currency-flag" aria-hidden="true">{{ $activeCurrencyMeta['flag'] }}</span><span>{{ $displayCurrency }}</span><i class="bi bi-chevron-down public-action-chevron"></i></button><div class="dropdown-menu dropdown-menu-end currency-switcher-menu p-2">@foreach($supportedCurrencies as $currencyCode)@php($currencyMeta = \App\Support\CurrencyMetadata::for($currencyCode))<form method="POST" action="{{ route('currency.update') }}">@csrf<input type="hidden" name="currency" value="{{ $currencyCode }}"><button class="dropdown-item currency-switcher-option {{ $displayCurrency === $currencyCode ? 'active' : '' }}" type="submit"><span>{{ $currencyMeta['flag'] }}</span><span><strong>{{ $currencyCode }}</strong><small>{{ $currencyMeta['country'] }}</small></span>@if($displayCurrency === $currencyCode)<i class="bi bi-check2"></i>@endif</button></form>@endforeach</div></div>
                @auth
                    <a class="public-header-action" href="{{ route('account.bookings.index') }}"><i class="bi bi-person-circle"></i><span>Account</span></a>
                @else
                    <a class="public-header-action" href="{{ route('login') }}"><i class="bi bi-person-circle"></i><span>Sign in</span></a>
                @endauth
                <button class="public-menu-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#publicMenuPanel" aria-controls="publicMenuPanel" aria-label="Open navigation menu"><i class="bi bi-list"></i></button>
            </div>
        </div>
    </nav>
    <div class="offcanvas offcanvas-end public-menu-panel" tabindex="-1" id="publicMenuPanel" aria-labelledby="publicMenuPanelLabel">
        <div class="offcanvas-header public-menu-header">
            <a class="public-brand" href="{{ route('home') }}" id="publicMenuPanelLabel"><img class="public-brand-logo" src="{{ asset('karossy-logo-dark.svg') }}" alt="Karossy Travels and Tours Limited" width="372" height="86"></a>
            <button type="button" class="public-menu-close" data-bs-dismiss="offcanvas" aria-label="Close navigation menu"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="offcanvas-body public-menu-body">
            <nav aria-label="Travel services">
                <span class="public-menu-eyebrow">Explore Karossy</span>
                <div class="public-menu-links public-menu-products">
                    <a href="{{ $serviceUrl('flights') }}" data-header-service="flights"><span><i class="bi bi-airplane-fill"></i><strong>Flights</strong></span><i class="bi bi-chevron-right"></i></a>
                    <a href="{{ $serviceUrl('hotels') }}" data-header-service="hotels"><span><i class="bi bi-building-fill"></i><strong>Hotels</strong></span><i class="bi bi-chevron-right"></i></a>
                    <a href="{{ route('cars.hire') }}"><span><i class="bi bi-car-front-fill"></i><strong>Car hire</strong></span><i class="bi bi-chevron-right"></i></a>
                    <a href="{{ route('visas.enquiry') }}"><span><i class="bi bi-passport-fill"></i><strong>Visa enquiries</strong></span><i class="bi bi-chevron-right"></i></a>
                    <a href="{{ $serviceUrl('charter') }}" data-header-service="charter"><span><i class="bi bi-airplane-engines-fill"></i><strong>Charter</strong></span><i class="bi bi-chevron-right"></i></a>
                    <a href="{{ route('holidays.index') }}"><span><i class="bi bi-sun-fill"></i><strong>Holiday packages</strong></span><i class="bi bi-chevron-right"></i></a>
                </div>
                <span class="public-menu-eyebrow public-menu-section-label">Plan and manage</span>
                <div class="public-menu-links public-menu-secondary">
                    <a href="{{ route('study-program') }}"><span><i class="bi bi-mortarboard-fill"></i><strong>Student Study Program</strong></span><i class="bi bi-chevron-right"></i></a>
                    <a href="{{ route('manage-booking.index') }}"><span><i class="bi bi-suitcase-lg"></i><strong>Manage booking</strong></span><i class="bi bi-chevron-right"></i></a>
                    <a href="mailto:{{ config('travel.support.email') }}"><span><i class="bi bi-headset"></i><strong>Help & support</strong></span><i class="bi bi-chevron-right"></i></a>
                    @auth<a href="{{ route('account.bookings.index') }}"><span><i class="bi bi-receipt"></i><strong>My bookings</strong></span><i class="bi bi-chevron-right"></i></a>@endauth
                </div>
            </nav>
            <div class="public-menu-account">
                @auth
                    <div><span class="public-menu-avatar"><i class="bi bi-person"></i></span><span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></span></div>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><i class="bi bi-box-arrow-right"></i> Sign out</button></form>
                @else
                    <p>Sign in to keep your trips together and manage bookings faster.</p>
                    <div class="d-flex gap-2"><a class="btn btn-karossy flex-grow-1" href="{{ route('login') }}">Sign in</a><a class="btn btn-outline-dark flex-grow-1" href="{{ route('register') }}">Create account</a></div>
                @endauth
            </div>
        </div>
    </div>
</header>
<main>
    @if (session('success'))<div class="container public-container pt-3"><div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button></div></div>@endif
    @yield('content')
</main>
<script type="application/ld+json">
{!! json_encode([
    chr(64).'context' => 'https://schema.org',
    chr(64).'graph' => [
        [
            chr(64).'type' => 'TravelAgency',
            chr(64).'id' => url('/').'#organization',
            'name' => 'Karossy Travels & Tours Limited',
            'url' => url('/'),
            'logo' => asset('karossy-logo-dark.svg'),
            'description' => 'Travel agency in Nigeria providing flights, hotels, holidays, visa support and travel management services.',
            'areaServed' => ['Nigeria', 'United Kingdom'],
            'email' => config('travel.support.email'),
            'telephone' => config('travel.support.phone'),
        ],
        [
            chr(64).'type' => 'WebSite',
            chr(64).'id' => url('/').'#website',
            'url' => url('/'),
            'name' => 'Karossy Travels',
            'publisher' => [chr(64).'id' => url('/').'#organization'],
            'potentialAction' => [chr(64).'type' => 'SearchAction', 'target' => route('book-flights').'?origin={origin}&destination={destination}', 'query-input' => ['required name=origin', 'required name=destination']],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@auth
    @if (auth()->user()->isAdmin() || auth()->user()->isB2b())
        <a class="public-admin-float" href="{{ route('admin.dashboard') }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-grid-fill"></i><span>Go to admin</span></a>
    @endif
@endauth
@if($whatsappUrl)
    <a class="public-whatsapp-float" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Chat with Karossy Travels on WhatsApp" title="Chat with us on WhatsApp"><i class="bi bi-whatsapp"></i></a>
@endif
<footer class="public-footer">
    <div class="container public-container">
        <div class="public-footer-grid">
            <div class="public-footer-brand-column">
                <a class="public-footer-brand" href="{{ route('home') }}"><img class="public-footer-logo" src="{{ asset('karossy-logo-white.svg') }}" alt="Karossy Travels and Tours Limited" width="372" height="86"></a>
                <p>Your trusted travel partner for flights, hotels, student travel, visas and carefully coordinated journeys.</p>
                <div class="public-footer-contact"><a href="mailto:{{ config('travel.support.email') }}"><i class="bi bi-envelope"></i>{{ config('travel.support.email') }}</a>@if(config('travel.support.phone'))<a href="tel:{{ preg_replace('/\s+/', '', config('travel.support.phone')) }}"><i class="bi bi-telephone"></i>{{ config('travel.support.phone') }}</a>@endif</div>
                @include('partials.footer-social-links')
            </div>
            <div><h2>Products</h2><nav><a href="{{ $serviceUrl('flights') }}">Flights</a><a href="{{ $serviceUrl('hotels') }}">Hotels</a><a href="{{ route('holidays.index') }}">Holiday packages</a><a href="{{ route('cars.hire') }}">Car hire</a><a href="{{ route('cars.partners') }}">Driver partners</a><a href="{{ route('visas.enquiry') }}">Visa enquiries</a><a href="{{ $serviceUrl('charter') }}">Charter flights</a><a href="{{ route('study-program') }}">Student Study Program</a></nav></div>
            <div><h2>Support</h2><nav><a href="mailto:{{ config('travel.support.email') }}?subject=Karossy%20Help%20Request">Help centre</a><a href="mailto:{{ config('travel.support.email') }}?subject=Karossy%20Travel%20Enquiry">Contact us</a><a href="{{ route('manage-booking.index') }}">Manage booking</a>@auth<a href="{{ route('account.bookings.index') }}">My bookings</a>@endauth<a href="mailto:{{ config('travel.support.email') }}?subject=Cancellation%20Policy%20Enquiry">Cancellation support</a></nav></div>
            <div><h2>Company</h2><nav><a href="{{ route('home') }}#about-karossy">About Karossy</a><a href="mailto:{{ config('travel.support.email') }}?subject=Business%20Travel%20Enquiry">Business travel</a><a href="mailto:{{ config('travel.support.email') }}?subject=Karossy%20Partnership%20Enquiry">Partner with us</a><a href="{{ route('study-program') }}">Study abroad</a><a href="mailto:{{ config('travel.support.email') }}?subject=Careers%20at%20Karossy">Careers</a></nav></div>
        </div>
        <div class="public-footer-bottom"><span>© {{ date('Y') }} Karossy Travels and Tours Limited. All rights reserved.</span><span>IATA certified · Travel better. Travel confidently.</span></div>
    </div>
</footer>
@stack('scripts')
</body>
</html>

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Flights, hotels and holidays') · Karossy Travels</title>
    <meta name="description" content="Search flights, hotels, holidays and travel services with Karossy Travels.">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-site">
<header class="public-header">
    <nav class="navbar navbar-expand-lg" aria-label="Main navigation">
        <div class="container public-container">
            <a class="public-brand" href="{{ route('home') }}" aria-label="Karossy Travels home">
                <span class="public-brand-mark"><img src="{{ asset('favicon.png') }}" alt="" width="34" height="34"></span>
                <span><strong>KAROSSY</strong><small>Travels & Tours</small></span>
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavigation" aria-controls="publicNavigation" aria-expanded="false" aria-label="Toggle navigation"><i class="bi bi-list fs-2"></i></button>
            <div class="collapse navbar-collapse" id="publicNavigation">
                <div class="navbar-nav public-primary-nav ms-lg-4">
                    <a class="nav-link" href="{{ route('home') }}#travel-search" data-header-service="hotels">Hotels</a>
                    <a class="nav-link active" href="{{ route('home') }}#travel-search" data-header-service="flights">Flights</a>
                    <a class="nav-link" href="{{ route('home') }}#travel-search" data-header-service="cars">Cars</a>
                    <a class="nav-link" href="{{ route('home') }}#travel-search" data-header-service="visas">Visas</a>
                    <a class="nav-link" href="{{ route('home') }}#travel-search" data-header-service="charter">Charter</a>
                </div>
                <div class="navbar-nav public-utility-nav ms-lg-auto align-items-lg-center">
                    @php
                        $displayCurrency = app(\App\Travel\Pricing\DisplayCurrencyResolver::class)->resolve(request());
                        $supportedCurrencies = ['NGN', 'USD'];
                    @endphp
                    <div class="dropdown"><button class="nav-link public-currency" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-globe2"></i> {{ $displayCurrency }}</button><div class="dropdown-menu dropdown-menu-end currency-switcher-menu p-2">@foreach($supportedCurrencies as $currencyCode)@php($currencyMeta = \App\Support\CurrencyMetadata::for($currencyCode))<form method="POST" action="{{ route('currency.update') }}">@csrf<input type="hidden" name="currency" value="{{ $currencyCode }}"><button class="dropdown-item currency-switcher-option {{ $displayCurrency === $currencyCode ? 'active' : '' }}" type="submit"><span>{{ $currencyMeta['flag'] }}</span><span><strong>{{ $currencyCode }}</strong><small>{{ $currencyMeta['country'] }}</small></span>@if($displayCurrency === $currencyCode)<i class="bi bi-check2"></i>@endif</button></form>@endforeach</div></div>
                    <a class="nav-link" href="mailto:{{ config('travel.support.email') }}">Help & support</a>
                    @auth
                        <a class="nav-link" href="{{ route('account.bookings.index') }}"><i class="bi bi-suitcase-lg"></i> My bookings</a>
                        <span class="nav-link"><i class="bi bi-person-circle"></i> {{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link" type="submit"><i class="bi bi-box-arrow-right"></i> Sign out</button></form>
                    @else
                        <a class="nav-link" href="{{ route('login') }}"><i class="bi bi-person-circle"></i> Sign in</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>
</header>
<main>
    @if (session('success'))<div class="container public-container pt-3"><div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button></div></div>@endif
    @yield('content')
</main>
@auth
    @if (auth()->user()->isAdmin() || auth()->user()->isB2b())
        <a class="public-admin-float" href="{{ route('admin.dashboard') }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-grid-fill"></i><span>Go to admin</span></a>
    @endif
@endauth
<footer class="public-footer"><div class="container public-container d-flex flex-column flex-md-row justify-content-between gap-2"><span>© {{ date('Y') }} Karossy Travels and Tours Limited</span><span>Travel better. Travel confidently.</span></div></footer>
@stack('scripts')
</body>
</html>

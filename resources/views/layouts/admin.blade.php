<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Operations') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="container-fluid px-0">
    <div class="row g-0">
        <aside class="col-12 col-lg-2 app-sidebar p-3 p-xl-4">
            <a class="d-flex align-items-center gap-3 text-white text-decoration-none mb-4" href="{{ route('admin.dashboard') }}">
                <span class="brand-mark"><img src="{{ asset('favicon.png') }}" width="30" height="30" alt=""></span>
                <span><strong class="d-block">Karossy</strong><small class="text-white-50">Operations</small></span>
            </a>
            @php
                $routeName = request()->route()?->getName() ?? '';
                $addon = request()->route('addon');
                $travelLog = request()->route('travelLog');
                $contextProduct = request()->input('type') ?: data_get($addon, 'type');
                $logProduct = request()->route('product') ?: data_get($travelLog, 'product');
                $activeSection = match (true) {
                    $routeName === 'admin.dashboard' => 'dashboard',
                    str_starts_with($routeName, 'admin.flights.'), str_starts_with($routeName, 'admin.fair-rules.') => 'flights',
                    str_starts_with($routeName, 'admin.hotels.') => 'hotels',
                    str_starts_with($routeName, 'admin.addons.') => $contextProduct === 'hotel' ? 'hotels' : 'flights',
                    str_starts_with($routeName, 'admin.travel-logs.') => $logProduct === 'hotel' ? 'hotels' : ($logProduct === 'flight' ? 'flights' : 'providers'),
                    str_starts_with($routeName, 'admin.visas.') => 'visas',
                    str_starts_with($routeName, 'admin.bookings.') => 'bookings',
                    str_starts_with($routeName, 'admin.customers.') => 'customers',
                    str_starts_with($routeName, 'admin.analytics.') => 'analytics',
                    str_starts_with($routeName, 'admin.providers.') => 'providers',
                    str_starts_with($routeName, 'admin.pricing.') => 'pricing',
                    str_starts_with($routeName, 'admin.users.'), str_starts_with($routeName, 'admin.roles.'), str_starts_with($routeName, 'admin.permissions.') => 'users',
                    str_starts_with($routeName, 'admin.settings.') => 'settings',
                    $routeName === 'admin.workspace' => (string) request()->route('section'),
                    default => null,
                };
            @endphp
            <nav class="nav flex-lg-column gap-1 admin-navigation">
                @foreach (config('admin_navigation') as $group)
                    @php
                        $isAllowed = (app()->isLocal() && ! auth()->check()) || auth()->user()?->hasPermission($group['permission']);
                        $matchesItem = function (array $item): bool {
                            if (! isset($item['route']) || ! request()->routeIs($item['active'] ?? $item['route'])) return false;

                            return collect($item['route_parameters'] ?? [])->every(function ($value, $key): bool {
                                $current = request()->route($key);
                                if ($current === null) $current = request()->query($key);

                                return (string) $current === (string) $value;
                            });
                        };
                        $isOpen = request()->routeIs('admin.workspace') && request()->route('section') === $group['slug'];
                        $isOpen = $isOpen || ($group['slug'] === 'dashboard' && request()->routeIs('admin.dashboard'));
                        $isOpen = $isOpen || collect($group['items'] ?? [])->contains($matchesItem);
                        $isOpen = $isOpen || $activeSection === $group['slug'];
                    @endphp
                    @if ($isAllowed)
                        @if (isset($group['route']))
                            <a class="nav-link {{ $activeSection === $group['slug'] || request()->routeIs($group['active'] ?? $group['route']) ? 'active' : '' }}" href="{{ route($group['route']) }}"><i class="bi {{ $group['icon'] }}"></i>{{ $group['label'] }}</a>
                        @else
                            <button class="nav-link sidebar-dropdown-toggle {{ $isOpen ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#nav-{{ $group['slug'] }}" aria-expanded="{{ $isOpen ? 'true' : 'false' }}" aria-controls="nav-{{ $group['slug'] }}">
                                <span><i class="bi {{ $group['icon'] }}"></i>{{ $group['label'] }}</span>
                                <i class="bi bi-chevron-down sidebar-chevron"></i>
                            </button>
                            <div class="collapse {{ $isOpen ? 'show' : '' }}" id="nav-{{ $group['slug'] }}">
                                <div class="nav flex-column sidebar-subnav">
                                    @foreach ($group['items'] as $item)
                                        @php
                                            $itemAllowed = app()->isLocal() || auth()->user()?->hasPermission($item['permission'] ?? $group['permission']);
                                            $itemUrl = isset($item['route']) ? route($item['route'], $item['route_parameters'] ?? []) : route('admin.workspace', ['section' => $group['slug'], 'page' => $item['slug']]);
                                            $itemActive = isset($item['route'])
                                                ? $matchesItem($item)
                                                : ($isOpen && request()->route('page') === $item['slug']);
                                        @endphp
                                        @if($itemAllowed)<a class="nav-link {{ $itemActive ? 'active' : '' }}" href="{{ $itemUrl }}">{{ $item['label'] }}</a>@endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                @endforeach
            </nav>
        </aside>
        <main class="col-12 col-lg-10 p-3 p-md-4 px-xl-4 py-xl-5">
            <div class="admin-topbar d-flex align-items-center justify-content-end gap-2 mb-4">
                @php
                    $displayCurrency = app(\App\Travel\Pricing\DisplayCurrencyResolver::class)->resolve(request());
                    $displayCurrencyMeta = \App\Support\CurrencyMetadata::for($displayCurrency);
                    $supportedCurrencies = config('travel.currency.supported', ['NGN', 'USD']);
                @endphp
                <div class="dropdown">
                    <button class="admin-currency-control" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Switch display currency">
                        <span>{{ $displayCurrencyMeta['flag'] }}</span><strong>{{ $displayCurrency }}</strong><i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end currency-switcher-menu p-2">
                        <div class="currency-switcher-heading"><strong>Display currency</strong><small>Used for searches and customer prices</small></div>
                        @foreach($supportedCurrencies as $currencyCode)
                            @php($currencyMeta = \App\Support\CurrencyMetadata::for($currencyCode))
                            <form method="POST" action="{{ route('currency.update') }}">
                                @csrf
                                <input type="hidden" name="currency" value="{{ $currencyCode }}">
                                <button class="dropdown-item currency-switcher-option {{ $displayCurrency === $currencyCode ? 'active' : '' }}" type="submit">
                                    <span>{{ $currencyMeta['flag'] }}</span><span><strong>{{ $currencyCode }}</strong><small>{{ $currencyMeta['country'] }}</small></span>@if($displayCurrency === $currencyCode)<i class="bi bi-check2"></i>@endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
                <a class="admin-topbar-link" href="{{ route('home') }}">
                    <i class="bi bi-box-arrow-up-right"></i><span>Go to website</span>
                </a>
                <button class="admin-topbar-icon position-relative" type="button" aria-label="Notifications" title="Notifications">
                    <i class="bi bi-bell"></i>
                    <span class="admin-notification-dot" aria-hidden="true"></span>
                </button>
                <div class="dropdown">
                    <button class="admin-user-control" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open user menu">
                        <span class="admin-user-avatar"><i class="bi bi-person-fill"></i></span>
                        <span class="admin-user-copy text-start">
                            <strong>{{ auth()->user()?->name ?? 'Administrator' }}</strong>
                            <small>{{ auth()->user() ? strtoupper(auth()->user()->account_type) : 'ADMIN' }}</small>
                        </span>
                        <i class="bi bi-chevron-down admin-user-chevron"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end admin-user-menu p-2">
                        <div class="px-2 py-2 border-bottom mb-1"><strong class="d-block small">{{ auth()->user()?->name ?? 'Administrator' }}</strong><small class="text-secondary">{{ auth()->user()?->email }}</small></div>
                        <a class="dropdown-item" href="{{ route('home') }}"><i class="bi bi-house me-2"></i>Public website</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button></form>
                    </div>
                </div>
            </div>
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-circle-fill me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
<script>
    document.addEventListener('click', function (e) {
        // Generic confirm for forms with data-confirm attribute
        var el = e.target.closest('[data-confirm]');
        if (!el) return;
        var msg = el.getAttribute('data-confirm') || 'Are you sure?';
        if (!confirm(msg)) e.preventDefault();
    });
</script>
</body>
</html>

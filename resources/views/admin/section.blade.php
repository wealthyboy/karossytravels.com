@extends('layouts.admin')

@section('title', $title)

@section('content')
<header class="mb-4">
    <p class="text-danger fw-semibold mb-1">KAROSSY OPERATIONS</p>
    <h1 class="h3 fw-bold mb-2">{{ $title }}</h1>
    <p class="text-secondary mb-0">{{ $description }}</p>
</header>

@if(isset($providerStatus))
    <div class="row g-3 mb-4">
        @foreach([
            ['Connection', $providerStatus['enabled'] ? 'Enabled' : 'Demo mode', $providerStatus['enabled'], 'bi-plug'],
            ['Environment', $providerStatus['environment'], true, 'bi-hdd-network'],
            ['Credentials', $providerStatus['credentials_configured'] ? 'Configured' : 'Needs attention', $providerStatus['credentials_configured'], 'bi-key'],
            ['Last activity', $providerStatus['last_activity'] ? \Illuminate\Support\Carbon::parse($providerStatus['last_activity'])->diffForHumans() : 'No activity yet', (bool) $providerStatus['last_activity'], 'bi-clock-history'],
        ] as [$label, $value, $healthy, $icon])
            <div class="col-sm-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body p-3 p-xxl-4 d-flex justify-content-between gap-3"><div><div class="metric-label mb-2">{{ $label }}</div><div class="h5 fw-semibold mb-0">{{ $value }}</div></div><span class="metric-icon"><i class="bi {{ $icon }}"></i></span></div></div></div>
        @endforeach
    </div>
    @php($providerTest = session('providerConnectionTest'))
    <div class="card content-card mb-4">
        <div class="card-body p-4 p-xl-5">
            <div class="d-flex flex-column flex-xl-row align-items-xl-start justify-content-between gap-3 mb-4">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="metric-icon"><i class="bi bi-key"></i></span>
                        <div>
                            <h2 class="h5 mb-1">Sabre authentication token</h2>
                            <p class="text-secondary mb-0">Admin diagnostic view. The full token below is the Bearer token Karossy uses for authenticated supplier API calls.</p>
                        </div>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.providers.sabre.test') }}">
                    @csrf
                    <button class="btn btn-karossy text-nowrap" type="submit">
                        <i class="bi bi-plug"></i> Test connection & refresh token
                    </button>
                </form>
            </div>

            @if($providerTest)
                <div class="alert {{ $providerTest['status'] === 'success' ? 'alert-success' : 'alert-danger' }} mb-4" role="alert">
                    <div class="d-flex gap-2">
                        <i class="bi {{ $providerTest['status'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' }} mt-1"></i>
                        <div>
                            <strong>{{ $providerTest['status'] === 'success' ? 'Sabre connection passed' : 'Sabre connection failed' }}</strong>
                            <div class="small mt-1" style="word-break: break-word;">{{ $providerTest['message'] }}</div>
                            <div class="small mt-1 opacity-75">Completed in {{ number_format((int) $providerTest['duration_ms']) }} ms.</div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="border rounded-4 p-3 h-100">
                        <small class="text-secondary d-block mb-1">Authentication scheme</small>
                        <strong>{{ $providerStatus['auth_scheme'] }}</strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-4 p-3 h-100">
                        <small class="text-secondary d-block mb-1">Token status</small>
                        <strong>{{ $providerStatus['access_token'] ? 'Available' : 'Not cached yet' }}</strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-4 p-3 h-100">
                        <small class="text-secondary d-block mb-1">Token expiry</small>
                        <strong>
                            {{ $providerStatus['token_expires_at'] ? \Illuminate\Support\Carbon::parse($providerStatus['token_expires_at'])->format('d M Y H:i:s') : 'Not available' }}
                        </strong>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold" for="sabre-access-token">Current access token</label>
                <textarea id="sabre-access-token" class="form-control font-monospace" rows="4" readonly spellcheck="false">{{ $providerStatus['access_token'] ?? 'No token is currently cached. Use “Test connection & refresh token” to request a fresh token from Sabre.' }}</textarea>
                <div class="form-text text-danger"><i class="bi bi-shield-lock"></i> Treat this token as a secret. Anyone with an active token may be able to call supplier APIs until it expires.</div>
            </div>

            <div class="border rounded-4 p-3 bg-light">
                <div class="small text-secondary mb-1">Token endpoint</div>
                <code class="d-block text-break">{{ $providerStatus['token_endpoint'] }}</code>
                <div class="small text-secondary mt-3 mb-1">Authenticated API header</div>
                <code class="d-block text-break">Authorization: Bearer {{ $providerStatus['access_token'] ?? '[token not available]' }}</code>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7"><div class="card content-card h-100"><div class="card-body p-4"><h2 class="h5">Connection activity</h2><p class="text-secondary">Operational totals from the last 24 hours. Supplier credentials remain server-side; the temporary access token is exposed only on this protected diagnostic page.</p><div class="row g-3 mt-1"><div class="col-sm-6"><div class="border rounded-4 p-4"><small class="text-secondary d-block mb-1">Successful calls</small><strong class="h3 text-success">{{ number_format($providerStatus['successful_calls']) }}</strong></div></div><div class="col-sm-6"><div class="border rounded-4 p-4"><small class="text-secondary d-block mb-1">Failed calls</small><strong class="h3 {{ $providerStatus['failed_calls'] ? 'text-danger' : 'text-success' }}">{{ number_format($providerStatus['failed_calls']) }}</strong></div></div></div></div></div></div>
        <div class="col-lg-5"><div class="card content-card h-100"><div class="card-body p-4 d-flex flex-column"><h2 class="h5">Operational tools</h2><p class="text-secondary">Review search, revalidation and booking requests without exposing supplier details to customers.</p><div class="d-grid gap-2 mt-auto"><a class="btn btn-karossy" href="{{ route('admin.travel-logs.index', ['product' => 'all']) }}"><i class="bi bi-journal-text"></i> Open API logs</a><a class="btn btn-outline-secondary" href="{{ route('admin.flights.search') }}"><i class="bi bi-airplane"></i> Test flight search</a><a class="btn btn-outline-secondary" href="{{ route('admin.hotels.search') }}"><i class="bi bi-building"></i> Test hotel search</a></div></div></div></div>
    </div>
@elseif(isset($services))
    <div class="card content-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-4">Service</th><th>API status</th></tr></thead>
                    <tbody>
                    @foreach ($services as $service)
                        <tr>
                            <td class="ps-4 py-3"><strong>{{ $service['name'] }}</strong><small class="d-block text-secondary">{{ $service['description'] }}</small></td>
                            <td><span class="status-dot {{ $service['enabled'] ? 'ready' : 'pending' }}"></span>{{ $service['enabled'] ? 'Development' : 'Planned' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@elseif(isset($analytics))
    @php
        $analyticsMoney = fn (int $minor) => '₦'.number_format($minor / 100, 2);
    @endphp
    <div class="row g-3 mb-4">
        @foreach([
            ['Revenue', $analyticsMoney($analytics['revenue_minor']), 'bi-cash-stack'],
            ['Bookings', number_format($analytics['bookings']), 'bi-receipt'],
            ['Search conversion', number_format($analytics['conversion'], 1).'%', 'bi-funnel'],
            ['Tickets issued', number_format($analytics['tickets_issued']), 'bi-ticket-perforated'],
            ['Average booking value', $analyticsMoney($analytics['average_booking_minor']), 'bi-calculator'],
            ['Failed API calls', number_format($analytics['failed_api_calls']), 'bi-exclamation-triangle'],
            ['Average API response', number_format($analytics['average_api_ms']).' ms', 'bi-speedometer'],
            ['Searches', number_format($analytics['searches']), 'bi-search'],
        ] as [$label,$value,$icon])
        <div class="col-sm-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body p-3 p-xxl-4 d-flex justify-content-between"><div><div class="metric-label mb-2">{{ $label }}</div><div class="h4 fw-semibold mb-0">{{ $value }}</div></div><span class="metric-icon"><i class="bi {{ $icon }}"></i></span></div></div></div>
        @endforeach
    </div>
    <div class="row g-4"><div class="col-lg-6"><div class="card content-card h-100"><div class="card-body p-4"><h2 class="h5">Booking sources</h2><p class="small text-secondary">Where confirmed demand entered Karossy.</p>@forelse($analytics['sources'] as $source)<div class="d-flex justify-content-between border-top py-3"><span>{{ str($source->source_name)->replace('_',' ')->headline() }}</span><strong>{{ number_format($source->aggregate) }}</strong></div>@empty<p class="text-secondary mb-0">No source data yet.</p>@endforelse</div></div></div><div class="col-lg-6"><div class="card content-card h-100"><div class="card-body p-4"><h2 class="h5">Most searched routes</h2><p class="small text-secondary">Demand signals, whether or not a booking was completed.</p>@forelse($analytics['top_routes'] as $route)<div class="d-flex justify-content-between border-top py-3"><span>{{ $route->route_name }}</span><strong>{{ number_format($route->aggregate) }}</strong></div>@empty<p class="text-secondary mb-0">No route searches yet.</p>@endforelse</div></div></div></div>
@elseif(isset($sourceStats))
    <div class="row g-4"><div class="col-lg-7"><div class="card content-card"><div class="card-body p-4"><h2 class="h5">Booking channels</h2><p class="small text-secondary">Website, mobile, B2B and administrator-created bookings.</p>@forelse($sourceStats as $source)<div class="d-flex align-items-center justify-content-between border-top py-3"><span>{{ str($source->source_name)->replace('_',' ')->headline() }}</span><span class="count-pill">{{ number_format($source->bookings_count) }}</span></div>@empty<p class="text-secondary mb-0">No attributed bookings yet.</p>@endforelse</div></div></div><div class="col-lg-5"><div class="card content-card"><div class="card-body p-4"><h2 class="h5">Campaign attribution</h2><p class="small text-secondary">UTM source takes priority, then the booking channel.</p>@forelse($campaignStats as $campaign)<div class="d-flex align-items-center justify-content-between border-top py-3"><span>{{ str($campaign->campaign_source)->replace('_',' ')->headline() }}</span><strong>{{ number_format($campaign->bookings_count) }}</strong></div>@empty<p class="text-secondary mb-0">No campaign data yet.</p>@endforelse</div></div></div></div>
@else
    @if(isset($bookings) && $bookings->isNotEmpty())
        @php
            $canManage = app()->isLocal() || auth()->user()?->hasPermission('bookings.manage');
            $sortUrl = fn (string $column) => '#';
            $sortIcon = fn (string $column) => 'bi-arrow-down-up';
        @endphp

        <div class="card content-card">
            <div class="card-header bg-white border-0 p-3 p-md-4">
                <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                    <div>
                        <h2 class="h5 mb-1">{{ $title }}</h2>
                        <p class="text-secondary mb-0">{{ $description }}</p>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table admin-data-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="table-check ps-4"><input class="form-check-input select-all-rows" type="checkbox" aria-label="Select all bookings"></th>
                            <th><a href="#">Reference <i class="bi {{ $sortIcon('reference') }}"></i></a></th>
                            <th>Booking locator</th><th>Source</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Booked</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bookings as $booking)
                            <tr>
                                <td class="table-check ps-4"><input class="form-check-input row-checkbox" type="checkbox" aria-label="Select booking {{ $booking->id }}"></td>
                                <td class="ps-4"><a href="{{ route('admin.flights.orders.show', $booking->order) }}"><strong>{{ data_get($booking->order, 'reference') }}</strong></a></td>
                                <td>{{ $booking->provider_locator }}</td><td><span class="badge text-bg-light border">{{ str($booking->source ?: 'unknown')->replace('_',' ')->headline() }}</span></td>
                                <td>{{ data_get($booking->order, 'customer.name') }}</td>
                                <td>{{ ucfirst($booking->status) }}</td>
                                <td>{{ $booking->created_at->format('d M Y H:i') }}</td>
                                <td class="text-end pe-4">
                                    @include('admin.partials.actions', [
                                        'showUrl' => route('admin.flights.orders.show', $booking->order),
                                        'modifyUrl' => route('admin.flights.orders.show', $booking->order) . '#modify',
                                        'cancelUrl' => route('admin.bookings.cancel', $booking),
                                        'deleteUrl' => '#',
                                        'canManage' => $canManage,
                                        'canDelete' => false,
                                        'canCancel' => $booking->status !== 'cancelled',
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-0 p-3 p-md-4 d-flex justify-content-between align-items-center">
                <small class="text-secondary">Showing {{ $bookings->count() }} recent bookings</small>
            </div>
        </div>
    @else
        <div class="card content-card">
            <div class="card-body text-center p-5">
                <div class="brand-mark mx-auto mb-3"><img src="{{ asset('favicon.png') }}" width="30" height="30" alt=""></div>
                <h2 class="h5">{{ $emptyTitle }}</h2>
                <p class="text-secondary mx-auto mb-0" style="max-width: 34rem">{{ $emptyDescription }}</p>
            </div>
        </div>
    @endif
@endif
@endsection

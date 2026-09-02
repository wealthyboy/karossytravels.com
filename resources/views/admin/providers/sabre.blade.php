@extends('layouts.admin')

@section('title', $title)

@section('content')
<header class="mb-4">
    <p class="text-danger fw-semibold mb-1">KAROSSY OPERATIONS</p>
    <h1 class="h3 fw-bold mb-2">{{ $title }}</h1>
    <p class="text-secondary mb-0">{{ $description }}</p>
</header>

<div class="row g-3 mb-4">
    @foreach([
        ['Connection', $providerStatus['enabled'] ? 'Enabled' : 'Demo mode', 'bi-plug'],
        ['Environment', $providerStatus['environment'], 'bi-hdd-network'],
        ['Credentials', $providerStatus['credentials_configured'] ? 'Configured' : 'Needs attention', 'bi-key'],
        ['Last activity', $providerStatus['last_activity'] ? \Illuminate\Support\Carbon::parse($providerStatus['last_activity'])->diffForHumans() : 'No activity yet', 'bi-clock-history'],
    ] as [$label, $value, $icon])
        <div class="col-sm-6 col-xl-3">
            <div class="card metric-card h-100">
                <div class="card-body p-3 p-xxl-4 d-flex justify-content-between gap-3">
                    <div>
                        <div class="metric-label mb-2">{{ $label }}</div>
                        <div class="h5 fw-semibold mb-0">{{ $value }}</div>
                    </div>
                    <span class="metric-icon"><i class="bi {{ $icon }}"></i></span>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card content-card mb-4">
    <div class="card-body p-4 p-xl-5">
        <div class="d-flex flex-column flex-xl-row align-items-xl-start justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-start gap-3">
                <span class="metric-icon"><i class="bi bi-key"></i></span>
                <div>
                    <h2 class="h5 mb-1">Sabre authentication token</h2>
                    <p class="text-secondary mb-0">Admin diagnostic view for the Bearer token Karossy uses on authenticated supplier API calls.</p>
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
                        <div class="small mt-1 text-break">{{ $providerTest['message'] }}</div>
                        <div class="small mt-1 opacity-75">Completed in {{ number_format((int) $providerTest['duration_ms']) }} ms.</div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-3 mb-4">
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
                    <strong>{{ $providerStatus['token_expires_at'] ? \Illuminate\Support\Carbon::parse($providerStatus['token_expires_at'])->format('d M Y H:i:s') : 'Not available' }}</strong>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold" for="sabre-access-token">Current access token</label>
            <textarea id="sabre-access-token" class="form-control font-monospace" rows="4" readonly spellcheck="false">{{ $providerStatus['access_token'] ?? 'No token is currently cached. Use "Test connection & refresh token" to request a fresh token from Sabre.' }}</textarea>
            <div class="form-text text-danger"><i class="bi bi-shield-lock"></i> Treat this token as a secret. It is displayed only on this protected administrator diagnostic page.</div>
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
    <div class="col-lg-7">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h2 class="h5">Connection activity</h2>
                <p class="text-secondary">Operational totals from the last 24 hours.</p>
                <div class="row g-3 mt-1">
                    <div class="col-sm-6"><div class="border rounded-4 p-4"><small class="text-secondary d-block mb-1">Successful calls</small><strong class="h3 text-success">{{ number_format($providerStatus['successful_calls']) }}</strong></div></div>
                    <div class="col-sm-6"><div class="border rounded-4 p-4"><small class="text-secondary d-block mb-1">Failed calls</small><strong class="h3 {{ $providerStatus['failed_calls'] ? 'text-danger' : 'text-success' }}">{{ number_format($providerStatus['failed_calls']) }}</strong></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card content-card h-100">
            <div class="card-body p-4 d-flex flex-column">
                <h2 class="h5">Operational tools</h2>
                <p class="text-secondary">Review supplier requests and run live diagnostics.</p>
                <div class="d-grid gap-2 mt-auto">
                    <a class="btn btn-karossy" href="{{ route('admin.travel-logs.index', ['product' => 'all']) }}"><i class="bi bi-journal-text"></i> Open API logs</a>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.flights.search') }}"><i class="bi bi-airplane"></i> Test flight search</a>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.hotels.search') }}"><i class="bi bi-building"></i> Test hotel search</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

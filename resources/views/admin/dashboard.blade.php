@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<header class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
    <div>
        <p class="text-danger fw-semibold mb-1">BUSINESS OVERVIEW</p>
        <h1 class="h3 fw-bold mb-1">Performance dashboard</h1>
        <p class="text-secondary mb-0">Flight ticketing, hotel fulfilment and operational work across B2C and B2B channels.</p>
    </div>
    <div class="d-flex gap-2">
        <select class="form-select form-select-sm dashboard-filter" aria-label="Sales channel"><option>All channels</option><option>B2C</option><option>B2B</option></select>
        <select class="form-select form-select-sm dashboard-filter" aria-label="Reporting period"><option>Last 30 days</option><option>This week</option><option>This month</option><option>This year</option></select>
    </div>
</header>

<section class="row g-3 mb-4" aria-label="Flight ticketing and hotel booking metrics">
    @foreach ($financialMetrics as $metric)
        <div class="col-sm-6 col-xl-3">
            <div class="card metric-card h-100"><div class="card-body p-3 p-xxl-4">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div><div class="metric-label mb-2">{{ $metric['label'] }}</div><div class="h3 fw-bold mb-1">{{ $metric['value'] }}</div></div>
                    <span class="metric-icon"><i class="bi {{ $metric['icon'] }}"></i></span>
                </div>
                <small class="text-secondary">{{ $metric['change'] }}</small>
            </div></div>
        </div>
    @endforeach
</section>

<div class="row g-4 mb-4">
    <section class="col-xl-8">
        <div class="card content-card h-100"><div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
                <div><h2 class="h5 mb-1">Revenue performance</h2><p class="small text-secondary mb-0">Daily revenue and growth across all sales channels.</p></div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge text-bg-light border">Growth {{ $customerMetrics['revenue_growth'] }}</span>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Revenue grouping"><button class="btn btn-dark">Day</button><button class="btn btn-outline-secondary">Week</button><button class="btn btn-outline-secondary">Month</button></div>
                </div>
            </div>
            <div class="revenue-chart" role="img" aria-label="Revenue for the last 14 days">
                <div class="chart-grid"><span>$30k</span><span>$20k</span><span>$10k</span><span>$0</span></div>
                <div class="chart-bars">
                    @foreach ($revenueTrend as $point)
                        <div class="chart-column" title="{{ $point['label'] }}: ${{ number_format($point['value'], 2) }}">
                            <div class="chart-bar" style="height: {{ max(2, $point['percentage']) }}%"></div>
                            @if ($loop->odd)<small>{{ $point['label'] }}</small>@endif
                        </div>
                    @endforeach
                </div>
                <div class="chart-empty"><i class="bi bi-bar-chart-line"></i><strong>No revenue recorded</strong><small>Revenue will plot here after successful bookings.</small></div>
            </div>
        </div></div>
    </section>

    <section class="col-xl-4">
        <div class="card content-card h-100"><div class="card-body p-4">
            <h2 class="h5 mb-1">Booking status</h2>
            <p class="small text-secondary mb-4">Current booking lifecycle distribution.</p>
            <div class="booking-ring mx-auto mb-4"><div><strong>{{ number_format($totalBookings) }}</strong><small>Total</small></div></div>
            <div class="row g-2">
                @foreach ($bookingStatuses as $status => $count)
                    <div class="col-6"><div class="status-stat"><span>{{ $status }}</span><strong>{{ $count }}</strong></div></div>
                @endforeach
            </div>
        </div></div>
    </section>
</div>

<div class="row g-4">
    <section class="col-xl-8">
        <div class="card content-card h-100">
            <div class="card-header bg-white border-0 p-4 pb-2"><h2 class="h5 mb-1">Needs attention</h2><p class="small text-secondary mb-0">Operational queues that can affect customers or revenue.</p></div>
            <div class="card-body p-3 p-md-4 pt-2"><div class="row g-3">
                @foreach ($operationalQueues as $queue)
                    <div class="col-md-6">
                        <a href="#" class="operation-item text-decoration-none">
                            <span class="operation-icon text-bg-{{ $queue['severity'] }}"><i class="bi {{ $queue['icon'] }}"></i></span>
                            <span class="flex-grow-1 text-dark">{{ $queue['label'] }}</span>
                            <strong class="text-dark">{{ $queue['value'] }}</strong><i class="bi bi-chevron-right text-secondary"></i>
                        </a>
                    </div>
                @endforeach
            </div></div>
        </div>
    </section>
    <section class="col-xl-4">
        <div class="card content-card h-100"><div class="card-body p-4">
            <h2 class="h5 mb-1">Customer & booking quality</h2><p class="small text-secondary mb-3">Retention and fulfilment indicators.</p>
            @foreach ([
                'Returning customers' => $customerMetrics['returning_customers'],
                'Cancellations' => $customerMetrics['cancellations'],
                'Refunds' => $customerMetrics['refunds'],
                'Average booking time' => $customerMetrics['average_booking_time'],
            ] as $label => $value)
                <div class="d-flex align-items-center justify-content-between border-top py-3"><span class="text-secondary">{{ $label }}</span><strong>{{ $value }}</strong></div>
            @endforeach
        </div></div>
    </section>
</div>
@endsection

@extends('layouts.admin')

@section('title', $product === 'all' ? 'All bookings' : str($product)->headline().' bookings')

@section('content')
@php
    $canManage = app()->isLocal() || auth()->user()?->hasPermission('bookings.manage');
    $sortUrl = fn (string $column) => url()->current().'?'.http_build_query(array_merge(request()->query(), [
        'sort' => $column,
        'direction' => request('sort') === $column && request('direction') !== 'asc' ? 'asc' : 'desc',
    ]));
    $sortIcon = fn (string $column) => request('sort') === $column
        ? (request('direction') === 'asc' ? 'bi-sort-up' : 'bi-sort-down')
        : 'bi-arrow-down-up';
    $productRoutes = [
        'all' => 'admin.bookings.index',
        'flight' => 'admin.bookings.flights',
        'hotel' => 'admin.bookings.hotels',
        'visa' => 'admin.bookings.visas',
    ];
    $statusLabels = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded'];
    $sourceLabels = ['website' => 'Website', 'mobile_app' => 'Mobile app', 'b2b_portal' => 'B2B portal', 'admin' => 'Administrator'];
    $newBookingRoute = $product === 'hotel' ? 'admin.hotels.search' : ($product === 'visa' ? 'admin.visas.index' : 'admin.flights.search');
    $newBookingLabel = $product === 'hotel' ? 'New hotel search' : ($product === 'visa' ? 'Visa services' : 'New flight booking');
@endphp

<header class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-3 mb-4">
    <div>
        <p class="text-danger fw-semibold mb-1">BOOKING OPERATIONS</p>
        <h1 class="h3 fw-bold mb-2">{{ $product === 'all' ? 'All bookings' : str($product)->headline().' bookings' }}</h1>
        <p class="text-secondary mb-0">Review reservations, ticketing, customers, sources and booking references in one place.</p>
    </div>
    <a href="{{ route($newBookingRoute) }}" class="btn btn-karossy">
        <i class="bi bi-plus-lg me-2"></i>{{ $newBookingLabel }}
    </a>
</header>

<nav class="booking-product-tabs mb-4" aria-label="Booking products">
    @foreach(['all' => 'All', 'flight' => 'Flights', 'hotel' => 'Hotels', 'visa' => 'Visas'] as $type => $label)
        <a href="{{ route($productRoutes[$type]) }}" class="{{ $product === $type ? 'active' : '' }}">
            {{ $label }} <span>{{ number_format($productCounts[$type] ?? 0) }}</span>
        </a>
    @endforeach
</nav>

<div class="row g-3 mb-4">
    @foreach([
        ['Total bookings', $summary['total'], 'bi-receipt'],
        ['Confirmed', $summary['confirmed'], 'bi-check-circle'],
        ['Pending', $summary['pending'], 'bi-hourglass-split'],
        ['Ticketed', $summary['ticketed'], 'bi-ticket-perforated'],
        ['Cancelled', $summary['cancelled'], 'bi-x-circle'],
    ] as [$label, $value, $icon])
        <div class="col-6 col-md-4 col-xl booking-summary-column">
            <div class="booking-summary-card">
                <span><i class="bi {{ $icon }}"></i></span>
                <div><small>{{ $label }}</small><strong>{{ number_format($value) }}</strong></div>
            </div>
        </div>
    @endforeach
</div>

<div class="card content-card admin-table-card">
    <div class="card-header bg-white border-0 p-3 p-md-4">
        <form method="GET" class="booking-filter-form" autocomplete="off">
            <div class="input-group admin-table-search booking-filter-search">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Reference, PNR, customer or email">
            </div>
            <select name="status" class="form-select" aria-label="Booking status">
                <option value="">All statuses</option>
                @foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach
            </select>
            <select name="source" class="form-select" aria-label="Booking source">
                <option value="">All sources</option>
                @foreach($sources as $source)<option value="{{ $source }}" @selected(request('source') === $source)>{{ $sourceLabels[$source] ?? str($source)->replace('_', ' ')->headline() }}</option>@endforeach
            </select>
            <select name="ticket_status" class="form-select" aria-label="Ticket status">
                <option value="">All ticket states</option>
                <option value="issued" @selected(request('ticket_status') === 'issued')>Ticket issued</option>
                <option value="pending" @selected(request('ticket_status') === 'pending')>Ticket pending</option>
                <option value="unticketed" @selected(request('ticket_status') === 'unticketed')>Unticketed</option>
                <option value="refunded" @selected(request('ticket_status') === 'refunded')>Ticket refunded</option>
            </select>
            <div class="booking-date-filter">
                <label for="booking-date-from">From</label>
                <input id="booking-date-from" type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
            </div>
            <div class="booking-date-filter">
                <label for="booking-date-to">To</label>
                <input id="booking-date-to" type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
            </div>
            <div class="booking-filter-actions">
                <button class="btn admin-search-button" type="submit"><i class="bi bi-funnel me-1"></i>Apply filters</button>
                @if(request()->hasAny(['q', 'status', 'source', 'ticket_status', 'date_from', 'date_to', 'sort', 'direction']))
                    <a href="{{ url()->current() }}" class="btn btn-light">Clear</a>
                @endif
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table admin-data-table booking-data-table align-middle mb-0">
            <thead><tr>
                <th class="ps-4"><a href="{{ $sortUrl('created_at') }}">Booking <i class="bi {{ $sortIcon('created_at') }}"></i></a></th>
                <th>Customer</th>
                <th>PNR / locator</th>
                <th><a href="{{ $sortUrl('source') }}">Source <i class="bi {{ $sortIcon('source') }}"></i></a></th>
                <th>Total</th>
                <th>Ticketing</th>
                <th><a href="{{ $sortUrl('status') }}">Status <i class="bi {{ $sortIcon('status') }}"></i></a></th>
                <th class="text-end pe-4">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($bookings as $booking)
                @php
                    $order = $booking->order;
                    $customerName = $order?->customerProfile?->full_name ?: data_get($order?->customer, 'name', 'Guest traveller');
                    $customerEmail = $order?->customerProfile?->email ?: data_get($order?->customer, 'email');
                    $issuedTicket = $booking->tickets->first(fn ($ticket) => $ticket->status === 'issued' || $ticket->issued_at);
                    $pendingTicket = $booking->tickets->firstWhere('status', 'pending');
                    $ticketState = $issuedTicket ? 'Issued' : ($pendingTicket ? 'Pending' : 'Unticketed');
                    $source = $booking->source ?: $order?->channel ?: 'unknown';
                @endphp
                <tr>
                    <td class="ps-4">
                        <a class="booking-reference-link" href="{{ route('admin.bookings.show', $booking) }}">{{ $order?->reference ?? str($booking->id)->limit(12) }}</a>
                        <small class="d-block text-secondary mt-1"><i class="bi {{ $booking->product_type === 'hotel' ? 'bi-building' : ($booking->product_type === 'visa' ? 'bi-passport' : 'bi-airplane') }} me-1"></i>{{ str($booking->product_type)->headline() }} · {{ $booking->created_at->format('d M Y, H:i') }}</small>
                    </td>
                    <td><strong class="booking-customer-name">{{ $customerName }}</strong>@if($customerEmail)<small class="d-block text-secondary">{{ $customerEmail }}</small>@endif</td>
                    <td><strong>{{ $booking->provider_locator ?: 'Awaiting locator' }}</strong></td>
                    <td><span class="booking-source-chip">{{ $sourceLabels[$source] ?? str($source)->replace('_', ' ')->headline() }}</span></td>
                    <td><strong>{{ $order?->currency ?? '—' }} {{ $order ? number_format($order->total_minor / 100, 2) : '—' }}</strong></td>
                    <td><span class="booking-ticket-state booking-ticket-{{ strtolower($ticketState) }}"><i class="bi bi-ticket-perforated"></i>{{ $ticketState }}</span></td>
                    <td><span class="booking-status booking-status-{{ $booking->status }}"><span></span>{{ ucfirst($booking->status) }}</span></td>
                    <td class="text-end pe-4">
                        <div class="table-actions">
                            <a href="{{ route('admin.bookings.show', $booking) }}" class="btn table-action-btn"><i class="bi bi-eye"></i><span>View</span></a>
                            @if($canManage && ! in_array($booking->status, ['cancelled', 'refunded', 'failed'], true))
                                <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" data-confirm="Cancel this booking?" class="d-inline">
                                    @csrf
                                    <button class="btn table-action-btn table-action-warning" type="submit"><i class="bi bi-x-circle"></i><span>Cancel</span></button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="booking-empty-state">
                    <i class="bi bi-search"></i><strong>No bookings match these filters</strong><span>Clear the filters or start a new booking search.</span>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card-footer bg-white border-0 p-3 p-md-4 d-flex justify-content-between align-items-center">
        <small class="text-secondary">Showing {{ $bookings->firstItem() ?? 0 }}–{{ $bookings->lastItem() ?? 0 }} of {{ $bookings->total() }}</small>
        {{ $bookings->links() }}
    </div>
</div>
@endsection

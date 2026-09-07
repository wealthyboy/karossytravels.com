@extends('layouts.admin')
@section('title', 'Flight booking '.$order->reference)
@section('content')
<header class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
	<div>
		<p class="text-danger fw-semibold mb-1">BOOKING CONFIRMED</p>
		<h1 class="h3 fw-bold mb-2">{{ $order->reference }}</h1>
		<p class="text-secondary mb-1">The airline booking is confirmed. Payment was not collected through the admin.</p>
		<p class="small text-secondary">Source: <strong>{{ strtoupper($order->channel ?? ($order->user_id ? 'admin' : 'site')) }}</strong></p>
	</div>
	<div class="d-flex gap-2">
		<a class="btn btn-outline-secondary" href="{{ route('admin.flights.search') }}"><i class="bi bi-search"></i> New flight search</a>
		<a class="btn btn-karossy" href="{{ route('admin.flights.orders.show', $order) }}"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
	</div>
</header>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="row g-4">
	<div class="col-lg-7">
		<section class="admin-panel-card">
			<h2>Booking confirmation</h2>

			@foreach($order->bookings as $booking)
				<div class="mb-4">
					<div class="d-flex justify-content-between align-items-start mb-2">
						<div>
							<h3 class="h6 mb-1">Flight reservation · <small class="text-secondary">Locator: <strong>{{ $booking->provider_locator }}</strong></small></h3>
							<p class="small text-secondary mb-0">Status: <span class="badge bg-{{ $booking->status === 'confirmed' ? 'success' : ($booking->status === 'cancelled' ? 'danger' : 'secondary') }}">{{ ucfirst($booking->status) }}</span></p>
						</div>
						<div>
							<form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}">@csrf<button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Cancel booking {{ $booking->provider_locator }}?')"><i class="bi bi-x-circle"></i> Cancel</button></form>
						</div>
					</div>

					{{-- Itinerary --}}
					@if(isset($booking->details['itinerary']) && is_array($booking->details['itinerary']))
						<div class="table-responsive mb-3">
							<table class="table table-sm mb-0">
								<thead class="table-light">
									<tr><th>From</th><th>To</th><th>Depart</th><th>Arrive</th><th>Flight</th><th>Duration</th></tr>
								</thead>
								<tbody>
								@foreach($booking->details['itinerary'] as $leg)
									<tr>
										<td>{{ $leg['origin'] ?? $leg['from'] ?? '' }}</td>
										<td>{{ $leg['destination'] ?? $leg['to'] ?? '' }}</td>
										<td>{{ isset($leg['departure_at']) ? \Carbon\Carbon::parse($leg['departure_at'])->format('d M Y H:i') : ($leg['departureDate'] ?? '') }}</td>
										<td>{{ isset($leg['arrival_at']) ? \Carbon\Carbon::parse($leg['arrival_at'])->format('d M Y H:i') : ($leg['arrival_at'] ?? '') }}</td>
										<td>{{ ($leg['marketing_airline'] ?? '') . ' ' . ($leg['flight_number'] ?? $leg['flight'] ?? '') }}</td>
										<td>{{ isset($leg['duration_minutes']) ? $leg['duration_minutes'].'m' : ($leg['duration'] ?? '') }}</td>
									</tr>
								@endforeach
								</tbody>
							</table>
						</div>
					@endif

					{{-- Passengers --}}
					<h4 class="h6 mt-2">Passengers</h4>
					<ul class="list-unstyled mb-2">
						@foreach($booking->travellers ?? [] as $trav)
							<li><strong>{{ ($trav['givenName'] ?? $trav['given_name'] ?? $trav['given'] ?? '') }} {{ $trav['surname'] ?? $trav['surname'] ?? '' }}</strong> <small class="text-secondary">({{ $trav['passengerCode'] ?? $trav['type'] ?? '' }})</small><br><small class="text-secondary">DOB: {{ $trav['birthDate'] ?? $trav['date_of_birth'] ?? '' }}</small></li>
						@endforeach
					</ul>

				</div>
			@endforeach

		</section>
	</div>

	<div class="col-lg-5">
		<section class="admin-panel-card">
			<h2>Order total</h2>
			<div class="admin-confirmed-total"><span>{{ $order->currency }}</span><strong>{{ number_format($order->total_minor/100, 2) }}</strong></div>
			<p class="small text-secondary">No payment record was created. Handle settlement using the approved back-office process.</p>

			<hr>

			<h3 class="h6">Customer & Contact</h3>
			@php $cust = is_array($order->customer) ? $order->customer : (is_string($order->customer) ? json_decode($order->customer, true) : []); @endphp
			<p class="mb-1"><strong>{{ data_get($cust, 'title') }} {{ data_get($cust, 'first_name') }} {{ data_get($cust, 'last_name') }}</strong></p>
			<p class="small text-secondary mb-1">Email: {{ data_get($cust, 'email') ?? data_get($cust, 'emails.0') }}</p>
			<p class="small text-secondary mb-0">Phone: {{ data_get($cust, 'phone') ?? data_get($cust, 'phones.0') }}</p>

			<hr>

			<h3 class="h6">Notes & Details</h3>
			@if($order->notes ?? false)
				<p class="small text-secondary">{{ $order->notes }}</p>
			@else
				<p class="small text-secondary">No internal notes</p>
			@endif

			<hr>

			<h3 class="h6">Created by</h3>
			<p class="small text-secondary mb-2">Channel: <strong>{{ strtoupper($order->channel ?? 'site') }}</strong>@if($order->user_id) <span class="text-muted">(user id: {{ $order->user_id }})</span>@endif</p>

			{{-- Payments --}}
			<h3 class="h6 mt-3">Payments</h3>
			@if($order->payments && $order->payments->isNotEmpty())
				<div class="table-responsive mb-2">
					<table class="table table-sm mb-0">
						<thead class="table-light"><tr><th>Method</th><th>Amount</th><th>Status</th><th>Received</th></tr></thead>
						<tbody>
						@foreach($order->payments as $p)
							<tr>
								<td>{{ $p->method ?? 'N/A' }}</td>
								<td>{{ $order->currency }} {{ number_format(($p->amount_minor ?? 0)/100, 2) }}</td>
								<td>{{ ucfirst($p->status ?? 'unknown') }}</td>
								<td>{{ $p->created_at ? $p->created_at->format('d M Y H:i') : '-' }}</td>
							</tr>
						@endforeach
						</tbody>
					</table>
				</div>
			@else
				<p class="small text-secondary">No payments recorded for this order.</p>
			@endif

			{{-- Tickets --}}
			<h3 class="h6 mt-3">Tickets</h3>
			@php $tickets = collect(); foreach($order->bookings as $b){ $tickets = $tickets->concat($b->tickets); } @endphp
			@if($tickets->isNotEmpty())
				<div class="table-responsive mb-2">
					<table class="table table-sm mb-0">
						<thead class="table-light"><tr><th>Ticket</th><th>Passenger</th><th>Issued</th><th>Status</th></tr></thead>
						<tbody>
						@foreach($tickets as $t)
							<tr>
								<td>{{ $t->ticket_number ?? $t->number ?? '—' }}</td>
								<td>{{ data_get($t, 'passenger_name') ?? '—' }}</td>
								<td>{{ $t->issued_at ? $t->issued_at->format('d M Y') : '—' }}</td>
								<td>{{ $t->voided_at ? 'Voided' : ($t->refunded_at ? 'Refunded' : 'Issued') }}</td>
							</tr>
						@endforeach
						</tbody>
					</table>
				</div>
			@else
				<p class="small text-secondary">No tickets issued yet.</p>
			@endif

			{{-- Fare / Offer details --}}
			<hr>
			<h3 class="h6 mt-3">Fare details</h3>
			@php $firstBooking = $order->bookings->first(); $fare = $firstBooking->details['fare_summary'] ?? $firstBooking->details['fare'] ?? null; @endphp
			@if($fare)
				<pre class="small p-2 bg-light border">{{ json_encode($fare, JSON_PRETTY_PRINT) }}</pre>
			@else
				<p class="small text-secondary">No fare breakdown available.</p>
			@endif

		</section>
	</div>
</div>
@endsection

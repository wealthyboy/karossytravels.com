@extends('layouts.admin')

@section('title', 'Booking '.($booking->order?->reference ?? $booking->provider_locator))

@section('content')
@php
    $order = $booking->order;
    $customer = $order?->customerProfile;
    $customerName = $customer?->full_name ?: data_get($order?->customer, 'name', 'Guest traveller');
    $customerEmail = $customer?->email ?: data_get($order?->customer, 'email');
    $customerPhone = $customer?->phone ?: data_get($order?->customer, 'phone');
    $canManage = app()->isLocal() || auth()->user()?->hasPermission('bookings.manage');
    $isClosed = in_array($booking->status, ['cancelled', 'refunded', 'failed'], true);
    $hasIssuedTicket = $booking->tickets->contains(fn ($ticket) => $ticket->status === 'issued' || $ticket->issued_at);
    $isNdc = filled(data_get($booking->travelOffer?->fare_summary, 'order_offer_id'));
    $isManualProviderWorkflow = $booking->product_type === 'flight' && strtolower($booking->provider) !== 'fake' && ! $isNdc;
    $itinerary = collect(data_get($booking->details, 'itinerary', []))->flatMap(function ($item) {
        if (! is_array($item)) return [];
        if (isset($item['origin']) || isset($item['from'])) return [$item];

        return collect($item)->filter(fn ($leg) => is_array($leg))->values()->all();
    });
@endphp

<header class="booking-view-header mb-4">
    <div>
        <a href="{{ url()->previous() }}" class="admin-back-link"><i class="bi bi-arrow-left"></i>Back to bookings</a>
        <p class="text-danger fw-semibold mb-1 mt-3">{{ str($booking->product_type)->upper() }} BOOKING</p>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <h1 class="h3 fw-bold mb-0">{{ $order?->reference ?? 'Booking details' }}</h1>
            <span class="booking-status booking-status-{{ $booking->status }}"><span></span>{{ ucfirst($booking->status) }}</span>
        </div>
        <p class="text-secondary mb-0 mt-2">Created {{ $booking->created_at->format('d M Y \a\t H:i') }} · {{ str($booking->source ?: $order?->channel ?: 'unknown')->replace('_', ' ')->headline() }}</p>
    </div>
    <div class="booking-view-actions">
        @if($booking->product_type === 'flight' && $order)
            <a class="btn btn-outline-secondary" href="{{ route('admin.flights.orders.show', $order) }}"><i class="bi bi-airplane me-2"></i>Full itinerary</a>
        @endif
        @if($canManage && ! $isClosed)
            <button class="btn btn-light" type="button" data-bs-toggle="modal" data-bs-target="#modifyBookingModal"><i class="bi bi-pencil-square me-2"></i>Modify</button>
            @if($booking->product_type === 'flight' && $hasIssuedTicket)
                <button class="btn btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#voidBookingModal"><i class="bi bi-ticket-perforated me-2"></i>Void ticket</button>
            @endif
            <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#cancelBookingModal" @disabled($hasIssuedTicket)><i class="bi bi-x-circle me-2"></i>Cancel booking</button>
        @endif
    </div>
</header>

@if($canManage && ! $isClosed && $hasIssuedTicket)
    <div class="booking-operator-note mb-4"><i class="bi bi-info-circle"></i><div><strong>This booking is ticketed.</strong><span>Void or refund the issued ticket before cancelling the itinerary.</span></div></div>
@elseif($canManage && ! $isClosed && $isManualProviderWorkflow)
    <div class="booking-operator-note mb-4"><i class="bi bi-headset"></i><div><strong>Manual confirmation applies.</strong><span>The request will be logged and emailed, but the local status will remain unchanged until a travel specialist confirms it was accepted.</span></div></div>
@elseif($booking->product_type === 'hotel' && $booking->status === 'pending')
    <div class="booking-operator-note mb-4"><i class="bi bi-building-check"></i><div><strong>Hotel confirmation required.</strong><span>The stay and customer price are recorded. Confirm the reservation before presenting a locator as final.</span></div></div>
@endif

<div class="row g-4">
    <div class="col-xl-8">
        <section class="card content-card mb-4">
            <div class="card-body p-4">
                <div class="booking-detail-heading"><div><span class="booking-detail-icon"><i class="bi {{ $booking->product_type === 'hotel' ? 'bi-building' : ($booking->product_type === 'visa' ? 'bi-passport' : 'bi-airplane') }}"></i></span><div><small>Booking confirmation</small><h2>{{ str($booking->product_type)->headline() }}</h2></div></div><div class="booking-locator-box"><small>PNR / locator</small><strong>{{ $booking->provider_locator ?: 'Pending' }}</strong><button type="button" data-copy-booking-locator="{{ $booking->provider_locator }}" aria-label="Copy booking locator"><i class="bi bi-copy"></i></button></div></div>
                <div class="booking-detail-grid mt-4">
                    <div><small>Product</small><strong>{{ str($booking->product_type)->headline() }}</strong></div>
                    <div><small>Booked</small><strong>{{ $booking->booked_at?->format('d M Y, H:i') ?? 'Not confirmed' }}</strong></div>
                    <div><small>Source</small><strong>{{ str($booking->source ?: $order?->channel ?: 'unknown')->replace('_', ' ')->headline() }}</strong></div>
                    <div><small>Booking status</small><strong>{{ ucfirst($booking->status) }}</strong></div>
                </div>
            </div>
        </section>

        @if($itinerary->isNotEmpty())
            <section class="card content-card mb-4"><div class="card-body p-4">
                <h2 class="booking-panel-title">Itinerary</h2>
                <div class="booking-itinerary-list">
                    @foreach($itinerary as $index => $leg)
                        <div class="booking-itinerary-row">
                            <span>{{ $index + 1 }}</span>
                            <div><small>From</small><strong>{{ data_get($leg, 'origin', data_get($leg, 'from', '—')) }}</strong><em>{{ data_get($leg, 'departure_at', data_get($leg, 'departureDate', '')) }}</em></div>
                            <i class="bi bi-arrow-right"></i>
                            <div><small>To</small><strong>{{ data_get($leg, 'destination', data_get($leg, 'to', '—')) }}</strong><em>{{ data_get($leg, 'arrival_at', data_get($leg, 'arrivalDate', '')) }}</em></div>
                            <div><small>Service</small><strong>{{ trim(data_get($leg, 'marketing_airline', '').' '.data_get($leg, 'flight_number', data_get($leg, 'flight', ''))) ?: '—' }}</strong></div>
                        </div>
                    @endforeach
                </div>
            </div></section>
        @endif

        @if($booking->product_type === 'hotel' && data_get($booking->details, 'stay'))
            <section class="card content-card mb-4"><div class="card-body p-4">
                <h2 class="booking-panel-title">Stay details</h2>
                <div class="booking-detail-grid mt-3">
                    <div><small>Property</small><strong>{{ data_get($booking->details, 'stay.hotel_name', '—') }}</strong></div>
                    <div><small>Room</small><strong>{{ data_get($booking->details, 'stay.room_name', '—') }}</strong></div>
                    <div><small>Check-in</small><strong>{{ data_get($booking->details, 'stay.check_in', '—') }}</strong></div>
                    <div><small>Check-out</small><strong>{{ data_get($booking->details, 'stay.check_out', '—') }}</strong></div>
                    <div><small>Guests</small><strong>{{ data_get($booking->details, 'stay.adults', 1) }} adult(s), {{ data_get($booking->details, 'stay.children', 0) }} child(ren)</strong></div>
                    <div><small>Rooms</small><strong>{{ data_get($booking->details, 'stay.rooms', 1) }}</strong></div>
                </div>
                @if(data_get($booking->details, 'special_requests'))<hr><small class="text-secondary">Special requests</small><p class="mb-0">{{ data_get($booking->details, 'special_requests') }}</p>@endif
            </div></section>
        @endif

        <section class="card content-card"><div class="card-body p-4">
            <h2 class="booking-panel-title">Tickets and services</h2>
            <div class="table-responsive"><table class="table admin-data-table mb-0"><thead><tr><th>Ticket number</th><th>Passenger</th><th>Status</th><th>Issued</th></tr></thead><tbody>
                @forelse($booking->tickets as $ticket)<tr><td>{{ $ticket->ticket_number ?: 'Awaiting issuance' }}</td><td>{{ $ticket->passenger_reference ?: '—' }}</td><td>{{ ucfirst($ticket->status) }}</td><td>{{ $ticket->issued_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                @empty<tr><td colspan="4" class="text-center text-secondary py-4">No tickets have been issued for this booking.</td></tr>@endforelse
            </tbody></table></div>
            @if($booking->addons->isNotEmpty())
                <h3 class="booking-panel-title mt-4">Selected add-ons</h3>
                @foreach($booking->addons as $addon)<div class="booking-addon-row"><div><strong>{{ $addon->title }}</strong><small>{{ $addon->description }}</small></div><b>{{ $addon->pivot->currency }} {{ number_format(($addon->pivot->price_cents * $addon->pivot->quantity) / 100, 2) }}</b></div>@endforeach
            @endif
        </div></section>

        <section class="card content-card mt-4"><div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3"><h2 class="booking-panel-title mb-0">Booking activity</h2><span class="booking-activity-count">{{ $booking->actions->count() }} actions</span></div>
            <div class="booking-activity-list">
                @forelse($booking->actions as $action)
                    <article class="booking-activity-item">
                        <span class="booking-activity-icon booking-activity-{{ $action->status }}"><i class="bi {{ $action->type === 'modify' ? 'bi-pencil-square' : ($action->type === 'void' ? 'bi-ticket-perforated' : 'bi-x-circle') }}"></i></span>
                        <div class="booking-activity-copy">
                            <div><strong>{{ str($action->type)->headline() }}</strong><span class="booking-action-status booking-action-status-{{ $action->status }}">{{ str($action->status)->headline() }}</span></div>
                            <p>{{ $action->reason }}</p>
                            @if($action->requested_change)<small><b>{{ str($action->change_type)->headline() }}:</b> {{ $action->requested_change }}</small>@endif
                            <em>{{ $action->created_at->format('d M Y, H:i') }} · {{ $action->user?->name ?? 'System' }} @if($action->customer_notified_at) · Customer emailed @endif</em>
                        </div>
                    </article>
                @empty
                    <div class="booking-activity-empty"><i class="bi bi-clock-history"></i><strong>No changes yet</strong><span>Modification, void and cancellation requests will appear here.</span></div>
                @endforelse
            </div>
        </div></section>
    </div>

    <aside class="col-xl-4">
        <section class="card content-card mb-4"><div class="card-body p-4">
            <h2 class="booking-panel-title">Order summary</h2>
            <dl class="booking-money-list">
                <div><dt>Fare subtotal</dt><dd>{{ $order?->currency ?? '—' }} {{ $order ? number_format($order->subtotal_minor / 100, 2) : '—' }}</dd></div>
                <div><dt>Fees & add-ons</dt><dd>{{ $order?->currency ?? '—' }} {{ $order ? number_format(($order->fees_minor - ($order->operator_markup_minor ?? 0)) / 100, 2) : '—' }}</dd></div>
                @if(($order?->operator_markup_minor ?? 0) > 0)<div><dt>Agent adjustment</dt><dd>{{ $order->currency }} {{ number_format($order->operator_markup_minor / 100, 2) }}</dd></div>@endif
                <div><dt>Discount</dt><dd>− {{ $order?->currency ?? '—' }} {{ $order ? number_format($order->discount_minor / 100, 2) : '—' }}</dd></div>
                <div class="total"><dt>Total</dt><dd>{{ $order?->currency ?? '—' }} {{ $order ? number_format($order->total_minor / 100, 2) : '—' }}</dd></div>
            </dl>
            <small class="text-secondary">Order status: <strong>{{ ucfirst($order?->status ?? 'unknown') }}</strong></small>
        </div></section>

        <section class="card content-card mb-4"><div class="card-body p-4">
            <h2 class="booking-panel-title">Customer</h2>
            <div class="booking-customer-summary"><span>{{ str($customerName)->substr(0, 1)->upper() }}</span><div><strong>{{ $customerName }}</strong>@if($customerEmail)<small>{{ $customerEmail }}</small>@endif @if($customerPhone)<small>{{ $customerPhone }}</small>@endif</div></div>
            @if($customer)<a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-light w-100 mt-3">View customer profile</a>@endif
        </div></section>

        <section class="card content-card"><div class="card-body p-4">
            <h2 class="booking-panel-title">Attribution</h2>
            <dl class="booking-attribution-list">
                <div><dt>Channel</dt><dd>{{ str($booking->source ?: $order?->channel ?: 'unknown')->replace('_', ' ')->headline() }}</dd></div>
                <div><dt>Campaign</dt><dd>{{ $booking->utm_campaign ?: 'Direct / none' }}</dd></div>
                <div><dt>UTM source</dt><dd>{{ $booking->utm_source ?: '—' }}</dd></div>
                <div><dt>UTM medium</dt><dd>{{ $booking->utm_medium ?: '—' }}</dd></div>
                <div><dt>Affiliate</dt><dd>{{ $booking->affiliate_id ?: '—' }}</dd></div>
            </dl>
        </div></section>
    </aside>
</div>

@if($canManage && ! $isClosed)
<div class="modal fade booking-action-modal" id="modifyBookingModal" tabindex="-1" aria-labelledby="modifyBookingTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('admin.bookings.modify', $booking) }}">@csrf
        <div class="modal-header"><div><small>BOOKING ACTION</small><h2 class="modal-title" id="modifyBookingTitle">Request a modification</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div class="booking-modal-callout"><i class="bi bi-arrow-repeat"></i><div><strong>Changes require a new quote.</strong><span>Record the requested change first. Confirm fare differences and availability before changing the PNR.</span></div></div>
            <label class="form-label" for="change_type">What needs to change?</label>
            <select class="form-select" id="change_type" name="change_type" required><option value="">Select change type</option><option value="dates">Travel dates</option><option value="route">Route or itinerary</option><option value="traveller">Traveller details</option><option value="contact">Contact details</option><option value="cabin">Cabin or fare</option><option value="other">Other</option></select>
            <label class="form-label mt-3" for="requested_change">Requested change</label><textarea class="form-control" id="requested_change" name="requested_change" rows="3" minlength="5" maxlength="3000" required placeholder="Describe exactly what should change"></textarea>
            <label class="form-label mt-3" for="modify_reason">Customer-facing reason or note</label><textarea class="form-control" id="modify_reason" name="reason" rows="2" minlength="3" maxlength="1000" required placeholder="This will be included in the email"></textarea>
            <label class="form-label mt-3" for="modify_notes">Internal note <span>optional</span></label><textarea class="form-control" id="modify_notes" name="internal_notes" rows="2" maxlength="3000" placeholder="Visible to staff only"></textarea>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep booking unchanged</button><button type="submit" class="btn btn-karossy"><i class="bi bi-send me-2"></i>Save and notify customer</button></div>
    </form></div>
</div>

<div class="modal fade booking-action-modal" id="voidBookingModal" tabindex="-1" aria-labelledby="voidBookingTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('admin.bookings.void', $booking) }}">@csrf
        <div class="modal-header"><div><small>TICKET ACTION</small><h2 class="modal-title" id="voidBookingTitle">Void issued ticket</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div class="booking-modal-callout is-warning"><i class="bi bi-exclamation-triangle"></i><div><strong>Voiding is time-sensitive.</strong><span>Only unused, open ticket coupons inside the airline's void window can be voided. The itinerary is retained after a successful void.</span></div></div>
            <label class="form-label" for="void_reason">Customer-facing reason or note</label><textarea class="form-control" id="void_reason" name="reason" rows="3" minlength="3" maxlength="1000" required></textarea>
            <label class="form-label mt-3" for="void_notes">Internal note <span>optional</span></label><textarea class="form-control" id="void_notes" name="internal_notes" rows="2" maxlength="3000"></textarea>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Go back</button><button type="submit" class="btn btn-warning"><i class="bi bi-ticket-perforated me-2"></i>Void and notify</button></div>
    </form></div>
</div>

<div class="modal fade booking-action-modal" id="cancelBookingModal" tabindex="-1" aria-labelledby="cancelBookingTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('admin.bookings.cancel', $booking) }}">@csrf
        <div class="modal-header"><div><small>BOOKING ACTION</small><h2 class="modal-title" id="cancelBookingTitle">Cancel booking</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div class="booking-modal-callout is-danger"><i class="bi bi-x-octagon"></i><div><strong>This removes the active itinerary.</strong><span>The local booking is marked cancelled only after the cancellation succeeds. Manually managed PNRs remain active until a specialist confirms the request.</span></div></div>
            <label class="form-label" for="cancel_reason">Customer-facing cancellation reason</label><textarea class="form-control" id="cancel_reason" name="reason" rows="3" minlength="3" maxlength="1000" required></textarea>
            <label class="form-label mt-3" for="cancel_notes">Internal note <span>optional</span></label><textarea class="form-control" id="cancel_notes" name="internal_notes" rows="2" maxlength="3000"></textarea>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep booking</button><button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-2"></i>Submit cancellation</button></div>
    </form></div>
</div>
@endif
@endsection

@push('scripts')
<script>
document.querySelector('[data-copy-booking-locator]')?.addEventListener('click', async function () {
    if (!this.dataset.copyBookingLocator) return;
    await navigator.clipboard.writeText(this.dataset.copyBookingLocator);
    const icon = this.querySelector('i');
    icon.className = 'bi bi-check-lg';
    window.setTimeout(() => icon.className = 'bi bi-copy', 1400);
});
</script>
@endpush

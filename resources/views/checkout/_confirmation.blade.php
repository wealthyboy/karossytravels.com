@php
    $segments = data_get($booking->details, 'itinerary', []);
    $travellers = $booking->travellers ?? [];
    $search = $booking->travelOffer?->flightSearch;
    $origin = $search?->origin ?: data_get($segments, '0.origin', 'Flight');
    $destination = $search?->destination ?: data_get($segments, (count($segments) - 1).'.destination', 'confirmed');
    $airlineCode = data_get($booking->travelOffer, 'fare_summary.validating_airline') ?: data_get($segments, '0.marketing_airline', 'AIR');
    $addonTotal = (int) data_get($booking->details, 'pricing.addons_minor', 0);
    $operatorAdjustment = (int) data_get($booking->details, 'pricing.operator_markup_minor', 0);
    $money = fn (int $minor) => \App\Support\CurrencyMetadata::format($minor, $order->currency);
    $ticketed = $booking->tickets->isNotEmpty();
@endphp
<section class="booking-page checkout-complete-page" data-booking-confirmation aria-live="polite">
    <div class="container public-container">
        @include('checkout._progress', ['step' => 3])
        <div class="confirmation-hero">
            <span class="completion-icon"><i class="bi bi-check-lg"></i></span>
            <div><span class="public-eyebrow">Reservation confirmed</span><h1>Your flight is booked</h1><p>Your booking was completed successfully. We have sent the confirmation to <strong>{{ data_get($order->customer, 'email') }}</strong>.</p></div>
            <span class="confirmation-status"><i class="bi bi-check-circle-fill"></i> Confirmed</span>
        </div>

        <div class="confirmation-reference-grid">
            <div class="confirmation-reference is-primary"><span><small>Airline PNR</small><strong data-copy-value>{{ $booking->provider_locator }}</strong></span><button type="button" aria-label="Copy airline PNR" data-copy-reference><i class="bi bi-copy"></i><span>Copy</span></button></div>
            <div class="confirmation-reference"><span><small>Karossy booking reference</small><strong>{{ $order->reference }}</strong></span><i class="bi bi-bookmark-check"></i></div>
            <div class="confirmation-reference"><span><small>Ticket status</small><strong>{{ $ticketed ? 'Ticket issued' : 'Awaiting ticket issuance' }}</strong></span><i class="bi bi-ticket-perforated"></i></div>
        </div>

        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <article class="confirmation-panel">
                    <div class="confirmation-panel-heading"><span class="airline-token">{{ $airlineCode }}</span><div><small>Complete itinerary</small><h2>{{ $origin }} <i class="bi bi-arrow-right"></i> {{ $destination }}</h2><p>{{ count($segments) }} {{ str('flight segment')->plural(count($segments)) }} · {{ count($travellers) }} {{ str('traveller')->plural(count($travellers)) }}</p></div></div>
                    <div class="confirmation-segments">
                        @foreach($segments as $index => $segment)
                            @php
                                $departure = data_get($segment, 'departure_at') ? \Carbon\Carbon::parse(data_get($segment, 'departure_at')) : null;
                                $arrival = data_get($segment, 'arrival_at') ? \Carbon\Carbon::parse(data_get($segment, 'arrival_at')) : null;
                                $duration = (int) data_get($segment, 'duration_minutes', 0);
                            @endphp
                            <div class="confirmation-segment">
                                <div class="confirmation-segment-number">{{ $index + 1 }}</div>
                                <div class="confirmation-place"><small>{{ $departure?->format('D, d M Y') }}</small><strong>{{ $departure?->format('H:i') ?: '—' }}</strong><span>{{ data_get($segment, 'origin') }}</span></div>
                                <div class="confirmation-flight-line"><small>{{ data_get($segment, 'flight_number') }}</small><span><i></i></span><em>{{ $duration ? intdiv($duration, 60).'h '.($duration % 60).'m' : ucfirst((string) data_get($segment, 'cabin', 'Flight')) }}</em></div>
                                <div class="confirmation-place is-arrival"><small>{{ $arrival?->format('D, d M Y') }}</small><strong>{{ $arrival?->format('H:i') ?: '—' }}</strong><span>{{ data_get($segment, 'destination') }}</span></div>
                                <div class="confirmation-segment-meta"><span><i class="bi bi-person-seat"></i> {{ str((string) data_get($segment, 'cabin', 'economy'))->replace('_', ' ')->headline() }}</span>@if(data_get($segment, 'booking_code'))<span><i class="bi bi-ticket"></i> Class {{ data_get($segment, 'booking_code') }}</span>@endif @if(data_get($segment, 'checked_baggage_pieces') !== null)<span><i class="bi bi-luggage"></i> {{ data_get($segment, 'checked_baggage_pieces') }} checked bag(s)</span>@endif</div>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="confirmation-panel mt-4">
                    <div class="confirmation-section-title"><span><i class="bi bi-people"></i></span><div><h2>Travellers</h2><p>Names recorded on this reservation</p></div></div>
                    <div class="confirmation-travellers">
                        @foreach($travellers as $index => $traveller)
                            <div><span>{{ $index + 1 }}</span><p><strong>{{ $traveller['title'] ?? '' }} {{ $traveller['first_name'] ?? '' }} {{ $traveller['last_name'] ?? '' }}</strong><small>{{ match($traveller['type'] ?? 'ADT') { 'ADT' => 'Adult', 'CNN' => 'Child', 'INF' => 'Infant', default => $traveller['type'] ?? 'Traveller' } }}@if(!empty($traveller['passport_number'])) · Passport ending {{ str($traveller['passport_number'])->take(-4) }}@endif</small></p><i class="bi bi-check-circle-fill"></i></div>
                        @endforeach
                    </div>
                </article>

                @if($booking->addons->isNotEmpty())
                    <article class="confirmation-panel mt-4"><div class="confirmation-section-title"><span><i class="bi bi-bag-check"></i></span><div><h2>Selected services</h2><p>Additional services attached to this booking</p></div></div><div class="confirmation-addons">@foreach($booking->addons as $addon)<div><span><strong>{{ $addon->title }}</strong><small>{{ $addon->description }}</small></span><b>{{ $money((int) $addon->pivot->price_cents) }}</b></div>@endforeach</div></article>
                @endif
            </div>

            <aside class="col-xl-4">
                <div class="confirmation-panel confirmation-summary">
                    <span class="public-eyebrow">Booking summary</span><h2>Total paid</h2><strong class="confirmation-total">{{ $money((int) $order->total_minor) }}</strong>
                    <div class="confirmation-price-lines"><div><span>Flight fare and taxes</span><strong>{{ $money((int) $order->subtotal_minor) }}</strong></div>@if($addonTotal > 0)<div><span>Additional services</span><strong>{{ $money($addonTotal) }}</strong></div>@endif @if($operatorAdjustment > 0)<div><span>Price adjustment</span><strong>{{ $money($operatorAdjustment) }}</strong></div>@endif</div>
                    <div class="confirmation-contact"><i class="bi bi-envelope-check"></i><span><small>Confirmation sent to</small><strong>{{ data_get($order->customer, 'email') }}</strong></span></div>
                    <a class="btn btn-karossy w-100" href="{{ route('account.bookings.show', $booking) }}"><i class="bi bi-receipt"></i> View booking</a>
                    <a class="btn btn-outline-dark w-100" href="{{ route('home') }}"><i class="bi bi-house"></i> Back to home</a>
                    <a class="confirmation-support" href="mailto:{{ config('travel.support.email') }}?subject=Booking%20{{ urlencode($order->reference) }}"><i class="bi bi-headset"></i> Need help? Contact support</a>
                </div>
            </aside>
        </div>
    </div>
</section>

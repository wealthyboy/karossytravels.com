@php
    $summarySegments = $offer->itinerary;
    $summaryFare = $offer->fare_summary;
    $summaryMoney = fn (int $minor) => ($currency === 'NGN' ? '₦' : '$').number_format($minor / 100, 2);
    $summarySearch = $offer->flightSearch;
@endphp
<div class="checkout-summary sticky-lg-top"><div class="mini-itinerary"><span class="airline-token">{{ $summaryFare['validating_airline'] ?: 'AIR' }}</span><div><strong>{{ $summarySearch->origin }} <i class="bi bi-arrow-right"></i> {{ $summarySearch->destination }}</strong><small>{{ count($summarySegments) }} flight {{ str('segment')->plural(count($summarySegments)) }}</small></div></div><div><span>Base fare and taxes</span><strong>{{ $summaryMoney($provider['amount_minor']) }}</strong></div><div><span>Karossy markup</span><strong>{{ $summaryMoney($markup['amount_minor']) }}</strong></div>@isset($addons)<div data-addon-summary class="d-none"><span>Selected add-ons</span><strong data-addon-summary-value>{{ $summaryMoney(0) }}</strong></div>@endisset<div class="summary-total"><span>Total</span><strong data-checkout-total>{{ $summaryMoney($total['amount_minor']) }}</strong></div><small>Displayed in {{ $currency }}. Prices include applicable taxes and fees.</small><p><i class="bi bi-shield-check"></i> Your details are securely protected</p></div>

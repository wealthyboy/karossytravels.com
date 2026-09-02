@if($addons->isNotEmpty())
<div class="booking-card mt-1">
    <div class="booking-card-title"><div><span class="booking-card-icon"><i class="bi bi-bag-plus"></i></span><div><h2>Add services to your trip</h2><p>Optional services are included only when selected.</p></div></div></div>
    <div class="checkout-addon-grid">
        @foreach($addons as $addon)
            @php($displayPrice = data_get($addon, 'display_price.amount_minor', $addon->price_cents))
            <label class="checkout-addon">
                <input class="form-check-input" type="checkbox" name="addons[]" value="{{ $addon->id }}" data-addon-price="{{ $displayPrice }}">
                @if($addon->image_path)<img src="{{ Storage::url($addon->image_path) }}" alt="">@else<span class="checkout-addon-icon"><i class="bi bi-stars"></i></span>@endif
                <span class="checkout-addon-copy"><strong>{{ $addon->title }}</strong><small>{{ $addon->description }}</small></span>
                <b>+{{ $money($displayPrice) }}</b>
            </label>
        @endforeach
    </div>
</div>
@endif

<div class="booking-card mt-1">
    <div class="booking-card-title"><div><span class="booking-card-icon"><i class="bi bi-file-earmark-check"></i></span><div><h2>Booking conditions</h2><p>{{ $airlineCode ?: 'Airline' }} fare conditions and Karossy service rules.</p></div></div></div>
    @forelse($fareRules as $rule)
        <details class="checkout-rule"><summary><span>{{ $rule->is_karossey_rule ? 'Karossy' : $rule->airline_code }}</span><strong>{{ $rule->title }}</strong><i class="bi bi-chevron-down"></i></summary><div>{!! nl2br(e($rule->content)) !!}</div></details>
    @empty
        <div class="checkout-rule-empty"><i class="bi bi-info-circle"></i><span><strong>Airline fare conditions apply</strong>The detailed fare conditions will be checked again before payment.</span></div>
    @endforelse
    <label class="form-check checkout-terms mt-3"><input class="form-check-input" name="terms" value="1" type="checkbox"><span class="form-check-label">I confirm the traveller information is correct and accept the airline rules, Karossy rules, cancellation policy and terms of service.</span></label>
    <div class="checkout-reservation-note"><i class="bi bi-shield-lock"></i><span><strong>{{ $demoPaymentEnabled ? 'Booking confirmation' : 'Secure online payment' }}</strong>{{ $demoPaymentEnabled ? 'The final live price will be checked before this development booking is completed.' : 'Your card details are handled securely. The live fare and exact total are verified before the reservation and PNR are created.' }}</span></div>
</div>

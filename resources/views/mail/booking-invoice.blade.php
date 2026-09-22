<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Booking invoice — {{ $order->reference }}</title>
<style>
  body { margin:0; padding:0; background:#f3f4f8; color:#17143f; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif; }
  .wrapper { width:100%; padding:32px 12px; }
  .invoice { width:100%; max-width:680px; margin:0 auto; background:#fff; border-radius:18px; overflow:hidden; box-shadow:0 8px 30px rgba(23,20,63,.08); }
  .header { padding:30px 38px; color:#fff; background:#17143f; border-bottom:5px solid #df0011; }
  .brand { font-size:23px; font-weight:900; letter-spacing:1px; }
  .brand-sub { margin-top:2px; color:#c9c7dc; font-size:10px; font-weight:700; letter-spacing:2px; text-transform:uppercase; }
  .header-label { margin:28px 0 6px; color:#ffb7bd; font-size:11px; font-weight:800; letter-spacing:1.5px; text-transform:uppercase; }
  .header h1 { margin:0; color:#fff; font-size:28px; line-height:1.15; }
  .header-meta { margin:9px 0 0; color:#d8d7e3; font-size:13px; }
  .body { padding:34px 38px 38px; }
  .greeting { margin:0 0 8px; font-size:17px; font-weight:800; }
  .intro { margin:0 0 24px; color:#5f6677; font-size:14px; line-height:1.7; }
  .status { display:inline-block; margin-bottom:22px; padding:7px 12px; border-radius:99px; color:#a30b16; background:#fff0f1; font-size:11px; font-weight:800; letter-spacing:.7px; text-transform:uppercase; }
  .panel { margin:0 0 24px; border:1px solid #e4e5ec; border-radius:14px; overflow:hidden; }
  .panel-title { padding:13px 18px; color:#17143f; background:#f7f7fa; font-size:12px; font-weight:800; letter-spacing:.9px; text-transform:uppercase; }
  .details { width:100%; border-collapse:collapse; }
  .details td { padding:12px 18px; border-top:1px solid #ececf1; color:#676d7b; font-size:13px; }
  .details td:last-child { color:#17143f; font-weight:800; text-align:right; }
  .total td { padding:18px; color:#fff; background:#17143f; border:0; }
  .total td:last-child { color:#fff; font-size:20px; }
  .segment { margin:0 0 9px; padding:13px 16px; border-radius:10px; background:#f7f7fa; }
  .route { color:#17143f; font-size:14px; font-weight:800; }
  .segment-meta { margin-top:4px; color:#6c7180; font-size:12px; line-height:1.5; }
  .bank { margin:0 0 24px; padding:22px; border:1px solid #dedfeb; border-left:5px solid #df0011; border-radius:12px; background:#fbfbfd; }
  .bank-eyebrow { color:#df0011; font-size:11px; font-weight:900; letter-spacing:1px; text-transform:uppercase; }
  .bank h2 { margin:5px 0 16px; color:#17143f; font-size:19px; }
  .bank-row { width:100%; border-collapse:collapse; }
  .bank-row td { padding:6px 0; color:#6b7080; font-size:13px; }
  .bank-row td:last-child { color:#17143f; font-weight:800; text-align:right; }
  .narration { margin:14px 0 0; padding-top:13px; border-top:1px solid #e3e4eb; color:#626878; font-size:12px; line-height:1.5; }
  .button { display:inline-block; padding:14px 24px; border-radius:9px; color:#fff !important; background:#df0011; font-size:14px; font-weight:800; text-decoration:none; }
  .notice { margin:22px 0 0; color:#747988; font-size:12px; line-height:1.6; }
  .footer { padding:22px 38px; color:#8a8e9b; background:#f6f6f9; font-size:11px; line-height:1.6; text-align:center; }
  .footer a { color:#17143f; }
  @media only screen and (max-width:600px) { .wrapper { padding:0; } .invoice { border-radius:0; } .header,.body { padding-left:22px; padding-right:22px; } .details td,.bank-row td { font-size:12px; } }
</style>
</head>
<body>
@php
  $bank = $settings->bankDetailsFor((string) $order->currency);
  $itinerary = data_get($booking->details, 'itinerary', []);
  $travellers = $booking->travellers ?? [];
@endphp
<table class="wrapper" role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td>
<div class="invoice">
  <div class="header">
    <div class="brand">KAROSSY</div>
    <div class="brand-sub">Travels &amp; Tours Limited</div>
    <div class="header-label">Booking invoice</div>
    <h1>Your flight is reserved.</h1>
    <p class="header-meta">Invoice {{ $order->reference }} · {{ now()->format('d M Y') }}</p>
  </div>

  <div class="body">
    <p class="greeting">Hello {{ data_get($order->customer, 'name', 'Traveller') }},</p>
    <p class="intro">Your flight has been placed on hold. Complete payment before <strong style="color:#17143f">{{ $order->payment_due_at?->format('D, d M Y H:i') }}</strong> to avoid automatic cancellation.</p>
    <span class="status">Pending payment</span>

    <div class="panel">
      <div class="panel-title">Booking summary</div>
      <table class="details" role="presentation" cellspacing="0" cellpadding="0">
        <tr><td>Karossy reference</td><td>{{ $order->reference }}</td></tr>
        <tr><td>Airline PNR</td><td>{{ $booking->provider_locator }}</td></tr>
        <tr><td>Payment deadline</td><td>{{ $order->payment_due_at?->format('d M Y, H:i') }}</td></tr>
        <tr class="total"><td>Total due</td><td>{{ \App\Support\CurrencyMetadata::format((int) $order->total_minor, $order->currency) }}</td></tr>
      </table>
    </div>

    @if(count($itinerary))
      <div style="margin-bottom:24px">
        <p style="margin:0 0 10px;font-size:14px;font-weight:800">Flight itinerary</p>
        @foreach($itinerary as $segment)
          <div class="segment">
            <div class="route">{{ data_get($segment, 'origin') }} → {{ data_get($segment, 'destination') }}</div>
            <div class="segment-meta">{{ data_get($segment, 'airline') }} {{ data_get($segment, 'flight_number') }}@if(data_get($segment, 'departure_at')) · {{ \Carbon\Carbon::parse(data_get($segment, 'departure_at'))->format('D, d M Y H:i') }}@endif @if(data_get($segment, 'cabin')) · {{ ucfirst(data_get($segment, 'cabin')) }}@endif</div>
          </div>
        @endforeach
      </div>
    @endif

    @if(count($travellers))
      <div class="panel">
        <div class="panel-title">Passengers</div>
        <table class="details" role="presentation" cellspacing="0" cellpadding="0">
          @foreach($travellers as $traveller)
            <tr><td>{{ trim(($traveller['title'] ?? '').' '.($traveller['first_name'] ?? '').' '.($traveller['last_name'] ?? '')) }}</td><td>{{ match($traveller['type'] ?? 'ADT') { 'ADT' => 'Adult', 'CNN' => 'Child', 'INF' => 'Infant', default => $traveller['type'] ?? 'Traveller' } }}</td></tr>
          @endforeach
        </table>
      </div>
    @endif

    <div class="bank">
      <div class="bank-eyebrow">{{ $bank['currency'] }} bank transfer</div>
      <h2>Payment account details</h2>
      <table class="bank-row" role="presentation" cellspacing="0" cellpadding="0">
        <tr><td>Bank</td><td>{{ $bank['bank_name'] ?: 'Contact support' }}</td></tr>
        <tr><td>Account name</td><td>{{ $bank['account_name'] ?: 'Contact support' }}</td></tr>
        <tr><td>Account number</td><td>{{ $bank['account_number'] ?: 'Contact support' }}</td></tr>
        @if($bank['sort_code'])<tr><td>Routing / identifier</td><td>{{ $bank['sort_code'] }}</td></tr>@endif
      </table>
      <p class="narration">Use <strong style="color:#17143f">{{ $order->reference }}</strong> as the transfer narration. These details match your selected <strong>{{ strtoupper($order->currency) }}</strong> booking currency.</p>
    </div>

    <p style="margin:0"><a class="button" href="{{ $paymentUrl }}">Make payment online&nbsp; →</a></p>
    <p class="notice">This is an invoice, not a receipt. A payment receipt will be sent after successful payment. If the hold deadline or departure date has passed, the payment link will no longer be accepted.</p>
  </div>

  <div class="footer">© {{ date('Y') }} Karossy Travels &amp; Tours Limited<br>Questions? <a href="mailto:{{ config('travel.support.email') }}">{{ config('travel.support.email') }}</a></div>
</div>
</td></tr></table>
</body>
</html>

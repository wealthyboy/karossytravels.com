<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Booking {{ $booking->status === 'confirmed' ? 'Confirmed' : 'Received' }} — {{ $order->reference }}</title>
<style>
  body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #18181b; }
  .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  .header { background: #9b1010; padding: 32px 40px; }
  .brand { border-collapse: collapse; margin-bottom: 24px; }
  .brand-mark { background: #ffffff; border-radius: 12px; padding: 6px; width: 44px; }
  .brand-name { color: #ffffff; font-size: 18px; font-weight: 800; letter-spacing: .8px; padding-left: 12px; }
  .brand-name span { color: rgba(255,255,255,.75); display: block; font-size: 10px; font-weight: 600; letter-spacing: 1.1px; margin-top: 2px; text-transform: uppercase; }
  .header h1 { margin: 0; font-size: 22px; color: #ffffff; letter-spacing: .5px; }
  .header p { margin: 6px 0 0; color: rgba(255,255,255,.75); font-size: 14px; }
  .body { padding: 36px 40px; }
  .greeting { font-size: 17px; font-weight: 600; margin-bottom: 8px; }
  .intro { color: #52525b; font-size: 15px; line-height: 1.6; margin-bottom: 28px; }
  .locator-box { background: #f9fafb; border: 1px solid #e4e4e7; border-radius: 8px; padding: 20px 24px; margin-bottom: 28px; display: flex; gap: 16px; align-items: center; }
  .locator-label { font-size: 12px; text-transform: uppercase; letter-spacing: .8px; color: #71717a; margin-bottom: 4px; }
  .locator-value { font-size: 24px; font-weight: 800; letter-spacing: 2px; color: #9b1010; font-family: monospace; }
  table.details { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
  table.details th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: .6px; color: #71717a; padding: 8px 0; border-bottom: 1px solid #e4e4e7; }
  table.details td { padding: 12px 0; border-bottom: 1px solid #f4f4f5; font-size: 14px; vertical-align: top; }
  table.details td:last-child { text-align: right; font-weight: 600; }
  .segment { background: #fafafa; border-radius: 6px; padding: 14px 18px; margin-bottom: 10px; font-size: 13px; }
  .segment .route { font-weight: 700; font-size: 15px; margin-bottom: 4px; }
  .segment .meta { color: #71717a; }
  .passengers { border-collapse: collapse; margin-bottom: 28px; width: 100%; }
  .passenger-name, .passenger-type { font-size: 14px; padding: 10px 0; }
  .passenger-type { color: #71717a; padding-left: 24px; text-align: right; white-space: nowrap; }
  .total-box { background: #9b1010; border-collapse: separate; border-radius: 8px; border-spacing: 0; color: #fff; margin-bottom: 28px; overflow: hidden; width: 100%; }
  .total-box .label { font-size: 14px; opacity: .85; }
  .total-box .amount { font-size: 22px; font-weight: 800; }
  .note { background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 14px 18px; font-size: 13px; color: #78350f; margin-bottom: 28px; }
  .footer { padding: 24px 40px; background: #f4f4f5; text-align: center; font-size: 12px; color: #a1a1aa; }
  .footer a { color: #9b1010; text-decoration: none; }
</style>
</head>
<body>
<div class="wrapper">
  <!-- Header -->
  <div class="header">
    <table class="brand" role="presentation" cellspacing="0" cellpadding="0" border="0">
      <tr>
        <td><img class="brand-mark" src="{{ asset('favicon.png') }}" width="44" height="44" alt="Karossy Travels"></td>
        <td class="brand-name">KAROSSY<span>Travels &amp; Tours Limited</span></td>
      </tr>
    </table>
    <h1>{{ $booking->product_type === 'hotel' ? '🏨' : '✈' }} Booking {{ $booking->status === 'confirmed' ? 'Confirmed' : 'Received' }}</h1>
    <p>Karossy Travels — {{ $booking->status === 'confirmed' ? 'Your trip is secured.' : 'Our team is processing your request.' }}</p>
  </div>

  <div class="body">
    <p class="greeting">Hello, {{ data_get($order->customer, 'name', 'Valued Traveller') }},</p>
    <p class="intro">
      @if($booking->status === 'confirmed') Great news — your {{ $booking->product_type }} booking has been confirmed.
      @else We have received your {{ $booking->product_type }} booking request and our operations team is completing the confirmation. @endif
      Please save your booking reference below; you'll need it when contacting us.
    </p>

    <!-- Booking reference / locator -->
    <div class="locator-box">
      <div>
        <div class="locator-label">Booking Reference</div>
        <div class="locator-value">{{ $order->reference }}</div>
      </div>
      <div style="margin-left: auto; text-align: right;">
        <div class="locator-label">Booking Locator</div>
        <div class="locator-value" style="font-size:18px;">{{ $booking->provider_locator }}</div>
      </div>
    </div>

    <!-- Itinerary -->
    @php $itinerary = data_get($booking->details, 'itinerary', []); $stay = data_get($booking->details, 'stay'); @endphp
    @if(count($itinerary))
    <p style="font-weight:700; margin-bottom:12px; font-size:15px;">Your Itinerary</p>
    @foreach($itinerary as $segment)
    <div class="segment">
      <div class="route">
        {{ data_get($segment, 'origin') }} → {{ data_get($segment, 'destination') }}
      </div>
      <div class="meta">
        {{ data_get($segment, 'airline') }} {{ data_get($segment, 'flight_number') }}
        @if(data_get($segment, 'departure_at'))
          &nbsp;·&nbsp; {{ \Carbon\Carbon::parse(data_get($segment, 'departure_at'))->format('D, d M Y H:i') }}
        @endif
        @if(data_get($segment, 'cabin'))
          &nbsp;·&nbsp; {{ ucfirst(data_get($segment, 'cabin')) }}
        @endif
      </div>
    </div>
    @endforeach
    @endif

    @if($stay)
    <p style="font-weight:700; margin-bottom:12px; font-size:15px;">Your Stay</p>
    <div class="segment"><div class="route">{{ data_get($stay, 'hotel_name') }}</div><div class="meta">{{ data_get($stay, 'check_in') }} → {{ data_get($stay, 'check_out') }} · {{ data_get($stay, 'rooms', 1) }} room(s) · {{ data_get($stay, 'room_name') }}</div></div>
    @endif

    <!-- Passengers -->
    @php $travellers = $booking->travellers ?? []; @endphp
    @if(count($travellers))
    <p style="font-weight:700; margin-bottom:12px; font-size:15px;">Passengers</p>
    <table class="passengers" role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
      @foreach($travellers as $traveller)
      <tr>
        <td class="passenger-name" style="font-size:14px; padding:10px 0; {{ $loop->last ? '' : 'border-bottom:1px solid #f4f4f5;' }}">
          {{ $traveller['title'] ?? '' }}
          {{ strtoupper($traveller['first_name'] ?? '') }}
          {{ strtoupper($traveller['last_name'] ?? '') }}
        </td>
        <td class="passenger-type" align="right" style="color:#71717a; font-size:14px; padding:10px 0 10px 24px; text-align:right; white-space:nowrap; {{ $loop->last ? '' : 'border-bottom:1px solid #f4f4f5;' }}">
          {{ match($traveller['type'] ?? 'ADT') { 'ADT' => 'Adult', 'CNN' => 'Child', 'INF' => 'Infant', default => $traveller['type'] } }}
        </td>
      </tr>
      @endforeach
    </table>
    @endif

    <!-- Order total -->
    <table class="total-box" role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#9b1010" style="background:#9b1010; border-radius:8px; color:#ffffff; margin-bottom:28px; width:100%;">
      <tr>
        <td class="label" style="color:#ffffff; font-size:14px; opacity:.85; padding:20px 12px 20px 24px;">Total Amount</td>
        <td class="amount" align="right" style="color:#ffffff; font-size:22px; font-weight:800; padding:20px 24px 20px 12px; text-align:right; white-space:nowrap;">{{ $order->currency }} {{ number_format($order->total_minor / 100, 2) }}</td>
      </tr>
    </table>

    <!-- Settlement and ticketing note -->
    <div class="note">
      @if(in_array($order->channel, ['admin', 'b2b'], true))
      <strong>Settlement note:</strong> This reservation was created through the Karossy operations portal.
      Settlement will be handled using the agreed account process.
      @else
      <strong>Ticketing note:</strong> Your airline reservation is confirmed. Ticket issuance and any outstanding
      settlement will be handled through the approved Karossy checkout process.
      @endif
      If you have any questions,
      please contact <a href="mailto:{{ config('travel.support.email') }}">{{ config('travel.support.email') }}</a>.
    </div>

    <!-- Booking details table -->
    <table class="details">
      <tr>
        <th>Detail</th>
        <th></th>
      </tr>
      <tr>
        <td>Status</td>
        <td>{{ $booking->status === 'confirmed' ? '✅ Confirmed' : '⏳ Pending confirmation' }}</td>
      </tr>
      <tr>
        <td>Booked on</td>
        <td>{{ $booking->booked_at?->format('D, d M Y \a\t H:i') }}</td>
      </tr>
      <tr>
        <td>Customer email</td>
        <td>{{ data_get($order->customer, 'email') }}</td>
      </tr>
    </table>
  </div>

  <div class="footer">
    <p>© {{ date('Y') }} Karossy Travels · <a href="{{ config('app.url') }}">karossytravels.com</a></p>
    <p>This email was sent because a booking was created for you. Questions? Email <a href="mailto:{{ config('travel.support.email') }}">{{ config('travel.support.email') }}</a>.</p>
  </div>
</div>
</body>
</html>

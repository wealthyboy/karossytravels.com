<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking update</title>
</head>
<body style="margin:0;background:#f4f5f7;color:#17152f;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;padding:28px 12px">
@php
    $booking = $bookingAction->booking;
    $order = $booking->order;
    $name = data_get($order?->customer, 'name', 'Traveller');
    $actionLabel = match($bookingAction->type) { 'modify' => 'Modification', 'void' => 'Ticket void', 'cancel' => 'Cancellation', default => 'Booking update' };
    $statusLabel = match($bookingAction->status) { 'completed' => 'Completed', 'failed' => 'Needs attention', default => 'Request received' };
    $statusColor = match($bookingAction->status) { 'completed' => '#187449', 'failed' => '#b42318', default => '#9a6700' };
@endphp
<div style="max-width:620px;margin:auto;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 12px 35px rgba(24,20,55,.09)">
    <div style="background:#17143f;padding:30px 36px;color:#fff">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;margin-bottom:22px">
            <tr>
                <td><img src="{{ asset('favicon.png') }}" width="44" height="44" alt="Karossy Travels" style="display:block;background:#fff;border-radius:12px;padding:6px"></td>
                <td style="padding-left:12px;color:#fff;font-size:18px;font-weight:800;letter-spacing:.8px">KAROSSY<span style="display:block;color:#c7c5dc;font-size:10px;font-weight:600;letter-spacing:1.1px;text-transform:uppercase;margin-top:2px">Travels &amp; Tours Limited</span></td>
            </tr>
        </table>
        <div style="font-size:12px;letter-spacing:1.4px;text-transform:uppercase;color:#ffccd1">Booking services</div>
        <h1 style="font-size:23px;margin:8px 0 0">{{ $actionLabel }} update</h1>
    </div>
    <div style="padding:34px 36px">
        <p style="font-size:17px;font-weight:700;margin:0 0 8px">Hello {{ $name }},</p>
        <p style="color:#5f6673;line-height:1.65;margin:0 0 24px">
            @if($bookingAction->status === 'completed')
                Your {{ strtolower($actionLabel) }} has been completed and recorded.
            @elseif($bookingAction->status === 'failed')
                We could not complete this action automatically. Our team has retained the request and will follow up before making any further change.
            @else
                We received your {{ strtolower($actionLabel) }} request. A travel specialist will confirm availability, charges and any fare difference before completing it.
            @endif
        </p>
        <div style="border:1px solid #e4e6eb;border-radius:14px;padding:20px 22px;margin-bottom:22px">
            <table style="border-collapse:collapse;width:100%;font-size:14px">
                <tr><td style="padding:7px 0;color:#747b88">Karossy reference</td><td style="padding:7px 0;text-align:right;font-weight:700">{{ $order?->reference }}</td></tr>
                <tr><td style="padding:7px 0;color:#747b88">Booking locator</td><td style="padding:7px 0;text-align:right;font-weight:700">{{ $booking->provider_locator ?: 'Pending' }}</td></tr>
                <tr><td style="padding:7px 0;color:#747b88">Action</td><td style="padding:7px 0;text-align:right;font-weight:700">{{ $actionLabel }}</td></tr>
                <tr><td style="padding:7px 0;color:#747b88">Status</td><td style="padding:7px 0;text-align:right;font-weight:800;color:{{ $statusColor }}">{{ $statusLabel }}</td></tr>
            </table>
        </div>
        <div style="background:#f7f7fa;border-radius:12px;padding:17px 19px;margin-bottom:22px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#747b88;margin-bottom:6px">Reason / customer note</div>
            <div style="font-size:14px;line-height:1.55">{{ $bookingAction->reason }}</div>
            @if($bookingAction->requested_change)
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#747b88;margin:15px 0 6px">Requested change</div>
                <div style="font-size:14px;line-height:1.55">{{ $bookingAction->requested_change }}</div>
            @endif
        </div>
        <p style="font-size:13px;line-height:1.6;color:#6f7682;margin:0">Do not make another booking for the same journey while this request is being reviewed. For urgent assistance, email <a href="mailto:{{ config('travel.support.email') }}" style="color:#b70d18">{{ config('travel.support.email') }}</a>.</p>
    </div>
    <div style="background:#f4f5f7;padding:20px 36px;text-align:center;color:#8a909a;font-size:12px">© {{ date('Y') }} Karossy Travels &amp; Tours Limited · <a href="mailto:{{ config('travel.support.email') }}" style="color:#9b1010;text-decoration:none">{{ config('travel.support.email') }}</a></div>
</div>
</body>
</html>

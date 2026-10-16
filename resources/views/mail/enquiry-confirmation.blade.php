<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Enquiry received</title></head>
<body style="background:#f4f7fb;color:#17143f;font-family:Arial,sans-serif;padding:24px">
<div style="background:#fff;max-width:600px;margin:auto;padding:28px;border-radius:16px">
    <h1 style="color:#0c2350;font-size:24px">Thank you for contacting {{ $settings->site_name }}.</h1>
    <p>Hello {{ $enquiry->name }},</p>
    <p>We have received your {{ strtolower($label) }} enquiry. Our team will review your message and contact you about the next steps.</p>
    <p><strong>Your reference: {{ $reference }}</strong></p>
    @if($enquiry->type === 'cancellation')<p>This acknowledgement does not cancel your booking or confirm a refund. Our team will review the booking conditions and confirm the available options.</p>@endif
    @if($enquiry->type === 'careers')<p>This confirms receipt of your interest only; it is not an offer of employment.</p>@endif
    <p>To follow up, email <a href="mailto:{{ $settings->contact_email }}">{{ $settings->contact_email }}</a> and quote your reference.</p>
    <p>{{ $settings->site_name }}<br>Your Satisfaction, Our Peace.</p>
</div></body></html>

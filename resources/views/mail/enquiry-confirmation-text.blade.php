Hello {{ $enquiry->name }},

Thank you for contacting {{ $settings->site_name }}. We have received your {{ strtolower($label) }} enquiry. Our team will review it and contact you about the next steps.

Your reference: {{ $reference }}
@if($enquiry->type === 'cancellation')
This acknowledgement does not cancel your booking or confirm a refund. Our team will review the booking conditions and confirm the available options.
@endif
@if($enquiry->type === 'careers')
This confirms receipt of your interest only; it is not an offer of employment.
@endif

To follow up, email {{ $settings->contact_email }} and quote your reference.

{{ $settings->site_name }}

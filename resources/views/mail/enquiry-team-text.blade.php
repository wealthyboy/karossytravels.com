New {{ $label }} enquiry
Reference: {{ $reference }}
Name: {{ $enquiry->name }}
Email: {{ $enquiry->email }}
Phone: {{ $enquiry->phone ?: 'Not provided' }}

{{ $enquiry instanceof \App\Models\ServiceEnquiry ? data_get($enquiry->details, 'message') : $enquiry->message }}

Review the full enquiry in the secure admin:
{{ $reviewUrl }}

Sign in with an authorised staff account. Sensitive travel and visa answers remain in the admin.

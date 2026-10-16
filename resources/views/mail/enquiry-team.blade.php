<!doctype html><html lang="en"><head><meta charset="utf-8"><title>New enquiry</title></head>
<body style="color:#17143f;font-family:Arial,sans-serif;padding:24px">
    <h1 style="font-size:24px">New {{ $label }} enquiry</h1>
    <p><strong>Reference:</strong> {{ $reference }}</p>
    <p><strong>Name:</strong> {{ $enquiry->name }}<br><strong>Email:</strong> {{ $enquiry->email }}<br><strong>Phone:</strong> {{ $enquiry->phone ?: 'Not provided' }}</p>
    @if($customerMessage = $enquiry instanceof \App\Models\ServiceEnquiry ? data_get($enquiry->details, 'message') : $enquiry->message)
        <p style="white-space:pre-wrap">{{ $customerMessage }}</p>
    @endif
    <p><a href="{{ $reviewUrl }}">Review the full enquiry in the secure admin</a></p>
    <p>Sign in with an authorised staff account to review all answers and update the enquiry status. Sensitive travel and visa answers remain in the admin.</p>
</body></html>

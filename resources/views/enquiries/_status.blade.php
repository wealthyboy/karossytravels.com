<div class="alert alert-danger" data-enquiry-error role="alert" hidden></div>
<div class="enquiry-success" data-enquiry-success role="status" tabindex="-1" @unless(session('enquiry_received')) hidden @endunless>
    <h2>{{ request()->routeIs('cars.*') ? 'Request received' : 'Enquiry received' }}</h2>
    <p data-enquiry-success-message>{{ data_get(session('enquiry_received'), 'message') }}</p>
    <p class="mb-0"><strong>Reference: <span data-enquiry-reference>{{ data_get(session('enquiry_received'), 'reference') }}</span></strong></p>
</div>

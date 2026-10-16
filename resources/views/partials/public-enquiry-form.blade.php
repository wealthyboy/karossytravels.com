@php
    $page = \App\Support\EnquiryForms::pages()[$enquiryType];
    $received = data_get(session('public_enquiry_received'), 'type') === $enquiryType ? session('public_enquiry_received') : null;
@endphp
<form id="{{ $formId }}" class="service-enquiry-form" action="{{ route($page['route'].'.store') }}" method="POST" aria-labelledby="{{ $formId }}-title" data-service-enquiry="{{ $enquiryType }}" novalidate>
    @csrf
    <h2 class="h5 fw-bold mb-3" id="{{ $formId }}-title">{{ $formTitle }}</h2>
    <div class="alert alert-danger" data-enquiry-error role="alert" @unless($errors->any()) hidden @endunless>{{ $errors->first() }}</div>
    <div class="enquiry-success" data-enquiry-success role="status" tabindex="-1" @unless($received) hidden @endunless>
        <h2>Enquiry received</h2><p data-enquiry-success-message>{{ data_get($received, 'message') }}</p>
        <p class="mb-0"><strong>Reference: <span data-enquiry-reference>{{ data_get($received, 'reference') }}</span></strong></p>
    </div>
    <div data-enquiry-fields @if($received) hidden @endif>
        @if($enquiryType === 'cancellation')<p class="alert alert-info">This sends a request for cancellation support. It does not automatically cancel your booking or confirm a refund.</p>@endif
        <div class="enquiry-grid">
            <x-enquiry-input name="full_name" label="Full name" required full autocomplete="name" maxlength="160" />
            <x-enquiry-input name="email" label="Email address" type="email" required autocomplete="email" maxlength="190" />
            <x-enquiry-input name="phone" label="Phone number" type="tel" autocomplete="tel" maxlength="40" />
            @foreach(\App\Support\EnquiryForms::fields($enquiryType) as $name => [$label, $required])
                <x-enquiry-input :name="$name" :label="$label" :required="$required" :type="$name === 'cv_url' ? 'url' : 'text'" :maxlength="$name === 'cv_url' ? 2048 : 200" />
            @endforeach
            <x-enquiry-input name="message" label="Your message" type="textarea" required full maxlength="5000" />
        </div>
        <div hidden aria-hidden="true"><label>Leave this blank<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <button class="btn btn-karossy enquiry-submit" type="submit"><span class="spinner-border spinner-border-sm" data-enquiry-spinner hidden aria-hidden="true"></span>Send enquiry <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
        <p class="enquiry-privacy">Your information is used by the Karossy team to review and respond to this enquiry.</p>
    </div>
</form>

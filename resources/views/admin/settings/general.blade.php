@extends('layouts.admin')
@section('title', 'General Settings')
@section('content')
<header class="mb-4">
    <p class="text-danger fw-semibold mb-1">SETTINGS</p>
    <h1 class="h3 fw-bold mb-2">General Settings</h1>
    <p class="text-secondary mb-0">Manage public contact links, WhatsApp, social media and all enquiry email recipients.</p>
</header>
@unless($ready)<div class="alert alert-warning">Run <code>php artisan migrate</code> to enable General Settings.</div>@endunless
@if($errors->any())
    <div class="alert alert-danger" role="alert"><strong>Please check the highlighted details.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<form method="POST" action="{{ route('admin.settings.general.update') }}">
    @csrf @method('PUT')
    <section class="card content-card mb-4">
        <div class="card-body p-4 p-lg-5">
            <h2 class="h5 fw-bold mb-2">Site and contact details</h2>
            <p class="text-secondary mb-4">The contact email and phone numbers appear in the website footer. The contact email also receives replies to customer acknowledgements. The WhatsApp number controls the floating bottom-right chat button.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="general-site-name">Site name</label>
                    <input class="form-control" id="general-site-name" name="site_name" maxlength="120" value="{{ old('site_name', $settings->site_name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="general-contact-email">Contact email</label>
                    <input class="form-control" type="email" id="general-contact-email" name="contact_email" maxlength="254" value="{{ old('contact_email', $settings->contact_email) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="general-phones">Phone numbers</label>
                    @php($oldPhones = old('phone_numbers', $settings->phone_numbers ?? []))
                    <textarea class="form-control" id="general-phones" name="phone_numbers" rows="3" aria-describedby="general-phones-help">{{ is_array($oldPhones) ? implode("\n", array_filter($oldPhones, 'is_string')) : $oldPhones }}</textarea>
                    <small class="text-secondary" id="general-phones-help">One number per line, including the country code. Maximum 10 numbers.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="general-whatsapp">WhatsApp number</label>
                    <input class="form-control" type="tel" id="general-whatsapp" name="whatsapp_number" maxlength="40" value="{{ old('whatsapp_number', $settings->whatsapp_number) }}" placeholder="+234…">
                    <small class="text-secondary">Include the country code. Leave blank to hide the floating WhatsApp button.</small>
                </div>
            </div>
        </div>
    </section>
    <section class="card content-card mb-4">
        <div class="card-body p-4 p-lg-5">
            <h2 class="h5 fw-bold mb-2">Enquiry email delivery</h2>
            <p class="text-secondary">Applies to contact, help, cancellation support, business travel, partnerships, careers, study abroad, car hire, visa enquiries and driver applications. Customers receive their own acknowledgement; the addresses below receive a separate team notification. Internal copy addresses are never included in the customer's email.</p>
            <div class="row g-3">
                @foreach(['enquiry_emails' => ['Send enquiries to (To)', []], 'enquiry_cc_emails' => ['Copy recipients (CC)', []], 'enquiry_bcc_emails' => ['Hidden copy recipients (BCC)', ['willie.samson@karossytravels.com']]] as $field => [$label, $fallback])
                    <div class="col-lg-4">
                        @php($addresses = old($field, $settings->getAttribute($field) ?? $fallback))
                        <label class="form-label" for="general-{{ $field }}">{{ $label }}</label>
                        <textarea class="form-control @if($errors->has($field) || $errors->has($field.'.*')) is-invalid @endif" id="general-{{ $field }}" name="{{ $field }}" rows="4" aria-describedby="{{ $field }}-help">{{ is_array($addresses) ? implode("\n", $addresses) : $addresses }}</textarea>
                        <small class="text-secondary" id="{{ $field }}-help">One email per line; maximum 10. @if($field === 'enquiry_emails')If blank, use the contact email above.@else Leave blank for no copies.@endif</small>
                    </div>
                @endforeach
            </div>
            <p class="small text-secondary mt-3 mb-0">CC recipients are visible to the other team recipients; BCC recipients are hidden. Previously requested BCC recipient Willie Samson is retained by default and can be changed or removed here. Emails use the site's existing mail transport; no SMTP credentials are exposed in this form.</p>
        </div>
    </section>
    <section class="card content-card mb-4">
        <div class="card-body p-4 p-lg-5">
            <h2 class="h5 fw-bold mb-2">Social media links</h2>
            <p class="text-secondary mb-4">Paste each full profile, page or channel URL, including <strong>https://</strong>. The original Facebook, Instagram, LinkedIn and YouTube icons stay visible; saving their URLs makes them clickable. Other platforms appear when a URL is saved. Clearing a URL removes its link.</p>
            <div class="row g-3">
                @foreach($platforms as $key => $platform)
                    <div class="col-md-6">
                        <label class="form-label" for="social-{{ $key }}"><i class="bi {{ $platform['icon'] }} me-2" aria-hidden="true"></i>{{ $platform['label'] }}</label>
                        <input class="form-control @error('social_links.'.$key) is-invalid @enderror" type="url" id="social-{{ $key }}" name="social_links[{{ $key }}]" maxlength="2048" value="{{ old('social_links.'.$key, data_get($settings->social_links, $key)) }}" placeholder="{{ $platform['example'] }}" autocomplete="url">
                        @error('social_links.'.$key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    <button class="btn btn-karossy" type="submit" @disabled(!$ready)><i class="bi bi-check2 me-2" aria-hidden="true"></i>Save general settings</button>
</form>
@endsection

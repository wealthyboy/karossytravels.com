@extends('layouts.admin')
@section('title', 'General Settings')
@section('content')
<header class="mb-4">
    <p class="text-danger fw-semibold mb-1">SETTINGS</p>
    <h1 class="h3 fw-bold mb-2">General Settings</h1>
    <p class="text-secondary mb-0">Keep your company details and public social media links in one place.</p>
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
            <p class="text-secondary mb-4">Save these details here. For now, only the social links below are connected to the public footer; existing site names and contact displays remain unchanged.</p>
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
                </div>
            </div>
        </div>
    </section>
    <section class="card content-card mb-4">
        <div class="card-body p-4 p-lg-5">
            <h2 class="h5 fw-bold mb-2">Social media links</h2>
            <p class="text-secondary mb-4">Paste each full profile, page or channel URL, including <strong>https://</strong>. Only saved links appear in the footer. Leave a field blank to hide that icon.</p>
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

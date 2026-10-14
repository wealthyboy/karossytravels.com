@extends('enquiries.layout')
@section('title', 'Visa Assistance & Enquiries in Nigeria')
@section('meta_description', 'Request personalised visa assistance from Karossy Travels. Tell our visa team about your trip for guidance on requirements, documentation, fees and next steps.')
@section('enquiry_heading', 'Visa Assistance')
@section('enquiry_lead', 'Planning to travel abroad? Let our visa team guide you through the application process.')
@section('enquiry_intro', 'Tell us about your intended trip and our team will review your requirements and contact you with the applicable visa requirements, documentation, fees and next steps.')
@section('enquiry_form')
@php
    $options = \App\Support\EnquiryOptions::class;
    $countries = array_values(\App\Support\CountryOptions::all());
    $purpose = (string) old('purpose_of_travel', '');
    $documentQuestion = $options::DOCUMENT_QUESTIONS[$purpose] ?? ['Do you have any supporting documents for this trip?', 'For example, an invitation letter, admission letter or employment letter. The question updates once you choose a purpose of travel.'];
@endphp
<form class="service-enquiry-form" action="{{ route('visas.enquiry.store') }}" method="POST" data-service-enquiry="visa_enquiry" novalidate>
    @csrf
    <script type="application/json" data-visa-enquiry-options>@json(['types' => collect(array_keys($options::VISA_TYPES))->mapWithKeys(fn ($purpose) => [$purpose => $options::visaTypes($purpose)]), 'documents' => $options::DOCUMENT_QUESTIONS])</script>
    @include('enquiries._status')
    <div data-enquiry-fields @if(session('enquiry_received')) hidden @endif>
        <div class="enquiry-grid">
            <h2>A. Applicant information</h2>
            <x-enquiry-input name="full_name" label="Full name" required full autocomplete="name" placeholder="Enter your full name" maxlength="160" />
            <x-enquiry-input name="email" label="Email address" type="email" required autocomplete="email" placeholder="Enter your email address" maxlength="190" />
            <x-enquiry-input name="phone_whatsapp" label="Phone / WhatsApp number" type="tel" required autocomplete="tel" placeholder="Enter your phone or WhatsApp number" maxlength="40" />
            <x-enquiry-select name="nationality" label="Nationality" :options="$countries" placeholder="Select your nationality" data-searchable-select data-search-placeholder="Start typing to search" />
            <x-enquiry-select name="country_of_residence" label="Country of residence" :options="$countries" placeholder="Select your country of residence" data-searchable-select data-search-placeholder="Start typing to search" />
            <h2>B. Travel information</h2>
            <x-enquiry-select name="destination_country" label="Destination country" :options="$countries" placeholder="Select your destination" data-searchable-select data-search-placeholder="Start typing to search" />
            <x-enquiry-select name="purpose_of_travel" label="Purpose of travel" :options="array_keys($options::VISA_TYPES)" placeholder="Select a purpose" />
            <x-enquiry-select name="visa_type" label="Visa type" :options="$options::visaTypes($purpose)" full placeholder="Select a purpose of travel first" hint="The options change to match your purpose of travel. Not sure? Choose ‘Not sure – please advise’." />
            <x-enquiry-input name="proposed_travel_date" label="Proposed travel date" type="date" required :min="today()->toDateString()" />
            <div class="enquiry-duration">
                <x-enquiry-input name="duration_number" label="Expected duration of stay" type="number" required min="1" max="999" placeholder="e.g. 2" />
                <x-enquiry-select name="duration_unit" label="Duration unit" :options="['Days', 'Weeks', 'Months']" placeholder="Select unit" value="Months" />
            </div>
            <x-enquiry-input name="number_of_applicants" label="Number of applicants" type="number" required min="1" max="50" value="1" />
            <h2>C. Applicant background</h2>
            <x-enquiry-select name="occupation_status" label="Current occupation / status" :options="$options::OCCUPATIONS" placeholder="Select your status" full />
            <x-enquiry-yes-no name="previous_travel" label="Have you travelled internationally before?">
                <div data-enquiry-conditional="previous_travel" @unless(old('previous_travel') === 'Yes') hidden @endunless><x-enquiry-input name="countries_previously_visited" label="Countries previously visited" placeholder="e.g. Ghana, UAE, United Kingdom" maxlength="1000" /></div>
            </x-enquiry-yes-no>
            <x-enquiry-yes-no name="previous_refusal" label="Have you ever been refused a visa?">
                <div data-enquiry-conditional="previous_refusal" @unless(old('previous_refusal') === 'Yes') hidden @endunless><x-enquiry-input name="refusal_details" label="Country and approximate date of refusal" placeholder="e.g. Canada, March 2024" hint="This helps our team make an accurate initial assessment." maxlength="1000" /></div>
            </x-enquiry-yes-no>
            <h2>D. Application requirements</h2>
            <x-enquiry-yes-no name="valid_passport" label="Do you have a valid passport?" />
            <x-enquiry-yes-no name="holds_valid_visa" label="Do you currently hold any valid visas?" />
            <x-enquiry-yes-no name="supporting_document" :label="$documentQuestion[0]" :hint="$documentQuestion[1]" full />
            <h2>E. Additional information</h2>
            <x-enquiry-select name="application_timeline" label="Preferred application timeline" :options="$options::TIMELINES" placeholder="Select a timeline" full />
            <x-enquiry-input name="additional_information" label="Additional information / questions" type="textarea" full maxlength="3000" placeholder="Please tell us anything else you would like our visa team to know about your application." />
        </div>
        <button class="btn btn-karossy enquiry-submit" type="submit"><span class="spinner-border spinner-border-sm" data-enquiry-spinner hidden aria-hidden="true"></span> Submit Visa Enquiry</button>
        <p class="enquiry-privacy">Your information is used by the Karossy visa team to review and respond to this enquiry. Please do not enter passport numbers or upload identity documents here.</p>
    </div>
</form>
<p class="enquiry-secondary-link">Already know the service you need? <a href="{{ route('visas.index') }}">Browse available visa services</a>.</p>
@endsection

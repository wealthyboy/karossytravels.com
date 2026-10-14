@extends('enquiries.layout')
@section('title', 'Car Hire Services in Nigeria')
@section('meta_description', 'Request airport transfers, chauffeur-driven cars, daily car hire and group transportation with Karossy Travels in Nigeria.')
@section('enquiry_heading', 'Car Hire Services')
@section('enquiry_lead', 'Reliable transportation, wherever your journey takes you.')
@section('enquiry_intro', 'Whether you need an airport transfer, a private vehicle for your trip, a chauffeur-driven car, or transportation for a group, Karossy Travels can arrange a vehicle based on your travel requirements.')
@section('enquiry_form')
<form class="service-enquiry-form" action="{{ route('cars.hire.store') }}" method="POST" data-service-enquiry="car_hire" novalidate>
    @csrf
    @include('enquiries._status')
    <div data-enquiry-fields @if(session('enquiry_received')) hidden @endif>
        <div class="enquiry-grid">
            <h2>Your details</h2>
            <x-enquiry-input name="full_name" label="Full name" required full autocomplete="name" placeholder="Enter your full name" maxlength="160" />
            <x-enquiry-input name="email" label="Email address" type="email" required autocomplete="email" placeholder="Enter your email address" maxlength="190" />
            <x-enquiry-input name="phone" label="Phone number" type="tel" required autocomplete="tel" placeholder="Enter your phone number" maxlength="40" />
            <h2>Trip details</h2>
            <x-enquiry-input name="pickup_location" label="Pick-up location" required placeholder="Where should we pick you up?" hint="For example: Murtala Muhammed International Airport, Lagos, a hotel name, a residential address, or another location." maxlength="500" />
            <x-enquiry-input name="dropoff_location" label="Drop-off location" required placeholder="Where are you going?" maxlength="500" />
            <x-enquiry-input name="pickup_date" label="Pick-up date" type="date" required :min="today()->toDateString()" />
            <x-enquiry-input name="pickup_time" label="Pick-up time" type="time" required />
            <x-enquiry-input name="return_end_date" label="Return / end date" type="date" hint="Required for round-trip transfers, daily car hire and chauffeur-driven car hire." />
            <x-enquiry-input name="return_end_time" label="Return / end time" type="time" hint="Required when a return / end date is provided." />
            <h2>Vehicle and passengers</h2>
            <x-enquiry-select name="hire_type" label="Type of car hire" :options="\App\Support\EnquiryOptions::HIRE_TYPES" placeholder="Select a type" />
            <x-enquiry-select name="vehicle_preference" label="Vehicle preference" :options="\App\Support\EnquiryOptions::VEHICLES" placeholder="Select a vehicle" />
            <x-enquiry-input name="passengers" label="Number of passengers" type="number" required min="1" max="100" value="1" />
            <x-enquiry-input name="luggage" label="Number of luggage / bags" type="number" min="0" max="100" value="0" />
            <x-enquiry-input name="additional_requirements" label="Additional requirements" type="textarea" full maxlength="3000" placeholder="Tell us anything else we should know about your transportation requirements." hint="For example: child seat required, multiple stops, special accessibility requirement, VIP/executive requirement, airport meet-and-greet, large luggage, or group transportation." />
        </div>
        <button class="btn btn-karossy enquiry-submit" type="submit"><span class="spinner-border spinner-border-sm" data-enquiry-spinner hidden aria-hidden="true"></span> Request Car Hire</button>
        <p class="enquiry-privacy">Your information is used by the Karossy team to review and respond to this request.</p>
    </div>
</form>
<p class="enquiry-secondary-link">Own a vehicle or drive professionally? <a href="{{ route('cars.partners') }}">Join our driver network</a>.</p>
@endsection

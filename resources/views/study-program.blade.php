@extends('layouts.public')

@section('title', 'Student Study Program')

@section('content')
<section class="study-program-hero">
    <div class="container public-container">
        <div class="study-program-hero-copy">
            <span class="public-eyebrow"><i class="bi bi-mortarboard-fill"></i> Study abroad with confidence</span>
            <h1>Your education journey, carefully planned.</h1>
            <p>From choosing the right programme to visas, flights and arrival support, Karossy helps students and families coordinate every important step.</p>
            <div class="study-program-actions">
                <a class="btn btn-karossy" href="mailto:{{ config('travel.support.email') }}?subject=Student%20Study%20Program%20Enquiry">Start an enquiry</a>
                <a class="btn btn-outline-dark" href="mailto:{{ config('travel.support.email') }}?subject=Student%20Study%20Program%20Consultation">Speak to an adviser</a>
            </div>
        </div>
        <div class="study-program-visual" aria-hidden="true">
            <span><i class="bi bi-mortarboard-fill"></i></span>
            <div><strong>Study abroad support</strong><small>Application to arrival</small></div>
        </div>
    </div>
</section>

<section class="study-program-section">
    <div class="container public-container">
        <div class="study-program-heading"><span class="public-eyebrow">How Karossy helps</span><h2>One team for the complete student journey</h2><p>Clear guidance, coordinated travel and practical support for students, parents and education partners.</p></div>
        <div class="study-program-grid">
            @foreach ([
                ['bi-search-heart', 'Programme guidance', 'Support with destinations, institutions and programmes suited to your goals.'],
                ['bi-file-earmark-check', 'Application support', 'A structured document checklist and guidance through each application stage.'],
                ['bi-passport', 'Visa assistance', 'Help preparing travel-document requirements and planning important appointments.'],
                ['bi-airplane', 'Student travel', 'Flight options, flexible fares and travel planning for students and accompanying family.'],
                ['bi-house-door', 'Arrival planning', 'Guidance for accommodation, airport transfers and settling into a new destination.'],
                ['bi-headset', 'Ongoing support', 'A dependable contact for questions before departure and after arrival.'],
            ] as [$icon, $title, $copy])
                <article><span><i class="bi {{ $icon }}"></i></span><h3>{{ $title }}</h3><p>{{ $copy }}</p></article>
            @endforeach
        </div>
    </div>
</section>

<section class="study-program-steps">
    <div class="container public-container">
        <div><span>1</span><strong>Tell us your goal</strong><small>Share your preferred country, course, budget and timeline.</small></div>
        <div><span>2</span><strong>Receive a clear plan</strong><small>We outline the next steps, required documents and travel considerations.</small></div>
        <div><span>3</span><strong>Prepare and travel</strong><small>Our team supports the journey from preparation through arrival.</small></div>
    </div>
</section>
@endsection

@extends('layouts.public')

@section('title', 'Page not found')

@section('content')
<section class="public-error-page">
    <div class="public-error-glow" aria-hidden="true"></div>
    <div class="container public-container position-relative">
        <div class="public-error-card">
            <span class="public-error-code">404</span>
            <div class="public-error-icon"><i class="bi bi-signpost-split"></i></div>
            <span class="public-eyebrow">Looks like a wrong turn</span>
            <h1>We couldn’t find that page.</h1>
            <p>The page may have moved, the address may be incorrect, or the journey may no longer be available.</p>
            <div class="public-error-actions">
                <a class="btn btn-karossy" href="{{ route('home') }}"><i class="bi bi-house-door"></i> Back to homepage</a>
                <a class="btn btn-outline-karossy" href="{{ route('home') }}#travel-search"><i class="bi bi-search"></i> Search travel</a>
            </div>
            <small>Need help? <a href="mailto:{{ config('travel.support.email') }}">Contact Karossy support</a></small>
        </div>
    </div>
</section>
@endsection

@extends('layouts.public')
@section('title', 'About Karossy')
@section('meta_description', 'Learn about Karossy Travels & Tours Limited, a Lagos-based travel destination management company serving individuals, families, businesses and groups.')
@section('content')
<section class="public-content-section home-about" aria-labelledby="about-title">
    <div class="container public-container">
        <div class="home-about-heading">
            <span class="public-eyebrow">About Karossy</span>
            <h1 class="mb-4" id="about-title">Your Satisfaction, Our Peace.</h1>
        </div>
        <div class="home-about-copy">
            @include('partials.about-copy')
        </div>
    </div>
</section>
@endsection

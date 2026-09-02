@extends('layouts.public')

@section('title', 'This travel option has expired')

@section('content')
<section class="service-complete-section">
    <div class="container public-container">
        <div class="service-complete-card">
            <span><i class="bi bi-clock-history"></i></span>
            <small>Availability update</small>
            <h1>This travel option has expired</h1>
            <p>Prices and availability change quickly. Start a fresh search to see the latest available options.</p>
            <a class="btn btn-karossy" href="{{ route('home') }}#travel-search">Search again <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
</section>
@endsection

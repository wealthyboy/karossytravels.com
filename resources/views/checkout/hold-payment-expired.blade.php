@extends('layouts.public')
@section('title', 'Booking date passed')
@section('content')<section class="booking-page"><div class="container public-container"><div class="booking-card text-center py-5"><span class="completion-icon"><i class="bi bi-calendar-x"></i></span><h1 class="h3 mt-3">Booking date passed</h1><p class="text-secondary">{{ $message }}</p><a class="btn btn-karossy" href="{{ route('home') }}">Return to homepage</a></div></div></section>@endsection

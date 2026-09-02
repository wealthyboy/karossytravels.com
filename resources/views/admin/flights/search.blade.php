@extends('layouts.admin')

@section('title', 'Search Flights')

@section('content')
<header class="mb-4"><p class="text-danger fw-semibold mb-1">FLIGHT OPERATIONS</p><h1 class="h3 fw-bold mb-1">Search flights</h1><p class="text-secondary mb-0">Search and price flight offers for Karossy customers and partners.</p></header>

<div data-admin-flight-search-page>
    <x-flight-search-form :departure-date="$defaultDepartureDate" :return-date="$defaultReturnDate" />
</div>
@endsection

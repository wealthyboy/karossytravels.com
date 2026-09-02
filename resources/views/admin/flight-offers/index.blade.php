@extends('layouts.admin')
@section('title', 'Flight offers')
@section('content')
<header class="admin-page-heading d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
    <div><span class="admin-eyebrow">HOMEPAGE DEALS</span><h1>Flight offers</h1><p>Manage the teaser fares that send customers into a live airline availability search.</p></div>
    <a class="btn btn-karossy" href="{{ route('admin.flight-offers.create') }}"><i class="bi bi-plus-lg"></i> New flight offer</a>
</header>
<div class="card content-card admin-table-card">
    <div class="table-responsive"><table class="table admin-data-table align-middle mb-0">
        <thead><tr><th class="ps-4">Route</th><th>Airline</th><th>Travel dates</th><th>From price</th><th>Visibility</th><th class="text-end pe-4">Actions</th></tr></thead>
        <tbody>@forelse($offers as $offer)
            <tr>
                <td class="ps-4"><div class="d-flex align-items-center gap-3">@if($offer->cover_url)<img class="rounded-3 object-fit-cover" width="72" height="54" src="{{ $offer->cover_url }}" alt="">@else<span class="brand-mark"><i class="bi bi-airplane"></i></span>@endif<span><strong>{{ $offer->origin_city }} → {{ $offer->destination_city }}</strong><small class="d-block text-secondary">{{ $offer->origin_airport }} → {{ $offer->destination_airport }}</small></span></div></td>
                <td><strong>{{ $offer->airline_name }}</strong>@if($offer->airline_code)<small class="d-block text-secondary">{{ $offer->airline_code }}</small>@endif</td>
                <td>{{ $offer->departure_date->format('M j') }} – {{ $offer->return_date->format('M j, Y') }}</td>
                <td>{{ $offer->currency }} {{ number_format($offer->price_minor / 100, 2) }}</td>
                <td><span class="status-pill {{ $offer->active ? 'status-active' : 'status-inactive' }}">{{ $offer->active ? 'Published' : 'Draft' }}</span><small class="d-block text-secondary mt-1">Order {{ $offer->sort_order }}</small></td>
                <td class="text-end pe-4"><div class="table-actions"><a class="table-action-btn" href="{{ route('admin.flight-offers.edit', $offer) }}"><i class="bi bi-pencil"></i><span>Edit</span></a><form method="POST" action="{{ route('admin.flight-offers.destroy', $offer) }}" data-confirm="Delete this flight offer?">@csrf @method('DELETE')<button class="table-action-btn text-danger"><i class="bi bi-trash3"></i><span>Delete</span></button></form></div></td>
            </tr>
        @empty<tr><td colspan="6" class="text-center text-secondary py-5">No flight offers have been created.</td></tr>@endforelse</tbody>
    </table></div>
    @if($offers->hasPages())<div class="card-footer bg-white border-0 p-4">{{ $offers->links() }}</div>@endif
</div>
@endsection

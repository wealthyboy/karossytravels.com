@extends('layouts.crm')

@section('title', 'Booking')
@section('topbar-title', 'Booking')

@section('content')
<div class="crm-page-heading"><div><p class="crm-eyebrow">Booking record</p><h1>{{ $booking->provider_locator ?: $booking->order?->reference ?: 'Booking' }}</h1><p>{{ ucfirst($booking->product_type) }} booking created {{ $booking->created_at?->diffForHumans() }}.</p></div><a class="crm-button crm-button-quiet" href="{{ route('crm.bookings.index') }}"><i class="bi bi-arrow-left"></i> All bookings</a></div>
<section class="crm-booking-detail-grid">
    <article class="crm-card">
        <div class="crm-card-heading"><div><span class="crm-card-kicker">Customer</span><h2>{{ $booking->order?->customerProfile?->full_name ?: data_get($booking->order?->customer, 'name', 'Guest booking') }}</h2></div><span class="crm-status {{ $booking->status }}">{{ ucfirst($booking->status) }}</span></div>
        <dl class="crm-booking-detail-list"><div><dt>Email</dt><dd>{{ $booking->order?->customerProfile?->email ?: data_get($booking->order?->customer, 'email', '—') }}</dd></div><div><dt>Service</dt><dd>{{ ucfirst($booking->product_type) }}</dd></div><div><dt>Provider</dt><dd>{{ $booking->provider }}</dd></div><div><dt>Reference</dt><dd>{{ $booking->provider_locator ?: $booking->order?->reference ?: '—' }}</dd></div></dl>
    </article>
    <article class="crm-card">
        <div class="crm-card-heading"><div><span class="crm-card-kicker">Customer ownership</span><h2>Assigned agent</h2></div></div>
        <form method="POST" action="{{ route('crm.bookings.assign', $booking) }}" class="crm-assignment-form">@csrf @method('PUT')<label>Agent<select name="assigned_user_id">@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected($booking->assigned_user_id === $agent->id)>{{ $agent->name }} · {{ $agent->email }}</option>@endforeach</select></label><button class="crm-button" type="submit"><i class="bi bi-person-check"></i> Save assignment</button></form>
        <p class="crm-detail-note"><i class="bi bi-clock"></i> {{ $booking->assigned_at ? 'Assigned '.$booking->assigned_at->diffForHumans() : 'Awaiting an eligible customer agent.' }}</p>
    </article>
</section>
<section class="crm-card crm-booking-timeline"><div class="crm-card-heading"><div><span class="crm-card-kicker">Booking timeline</span><h2>Handling activity</h2></div></div>@forelse($booking->activities as $activity)<div class="crm-booking-timeline-row"><span><i class="bi bi-check2"></i></span><div><strong>{{ $activity->subject ?: ucfirst($activity->type) }}</strong><p>{{ $activity->body }}</p><small>{{ $activity->actor?->name ?? 'System' }} · {{ $activity->occurred_at?->diffForHumans() }}</small></div></div>@empty<div class="crm-empty-table">The booking will show assignments, calls, notes and follow-ups here.</div>@endforelse</section>
@endsection

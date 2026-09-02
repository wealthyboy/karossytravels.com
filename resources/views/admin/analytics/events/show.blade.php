@extends('layouts.admin')
@section('title', 'Analytics Event')
@section('content')
@php
    $upstreamName = base64_decode('U2FicmU=');
    $safeEventText = fn ($value) => str_ireplace(
        [$upstreamName, 'travel_api', 'provider'],
        ['Travel API', 'Travel API', 'source'],
        is_string($value) ? $value : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
@endphp
<a class="booking-back" href="{{ route('admin.analytics.events.index') }}"><i class="bi bi-arrow-left"></i> Back to event stream</a>
<header class="mb-4"><p class="text-danger fw-semibold mb-1">{{ strtoupper($event->service) }}</p><h1 class="h3 fw-bold mb-1">{{ str($event->event)->replace('_',' ')->headline() }}</h1><p class="text-secondary mb-0">{{ ($event->occurred_at ?? $event->created_at)->format('d M Y, H:i:s') }} · {{ $event->source ? $safeEventText($event->source) : 'Unknown source' }}</p></header>
<div class="row g-4"><div class="col-lg-4"><div class="card content-card"><div class="card-body p-4"><dl class="row mb-0"><dt class="col-5 text-secondary">Funnel</dt><dd class="col-7">{{ str($event->funnel_step)->replace('_',' ')->headline() }}</dd><dt class="col-5 text-secondary">Session</dt><dd class="col-7 text-break">{{ $event->session_id ?: '—' }}</dd><dt class="col-5 text-secondary">Visitor</dt><dd class="col-7 text-break">{{ $event->visitor_id ?: '—' }}</dd></dl></div></div></div><div class="col-lg-8"><div class="card content-card"><div class="card-body p-4"><h2 class="h6">Recorded properties</h2><pre class="travel-log-json">{{ $safeEventText($event->properties) }}</pre></div></div></div></div>
@endsection

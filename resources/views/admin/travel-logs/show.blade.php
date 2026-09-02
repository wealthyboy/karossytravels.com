@extends('layouts.admin')

@section('title', 'Operational Log')

@section('content')
@php
    $upstreamName = base64_decode('U2FicmU=');
    $safeLogText = fn ($value) => str_ireplace(
        [$upstreamName, 'provider'],
        ['Travel API', 'source'],
        is_string($value) ? $value : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
@endphp
<a class="booking-back" href="{{ route('admin.travel-logs.index', $log->product_type) }}"><i class="bi bi-arrow-left"></i> Back to logs</a>
<header class="mb-4"><p class="text-danger fw-semibold mb-1">KAROSSY OPERATIONS</p><h1 class="h3 fw-bold mb-2">Request trace</h1><p class="text-secondary mb-0">{{ strtoupper($log->product_type) }} · {{ strtoupper($log->stage) }} · {{ $log->created_at->format('d M Y H:i:s') }} · Request ID {{ $log->request_id ?: 'not supplied' }}</p></header>

<div class="row g-4">
    <div class="col-xl-4"><div class="card content-card h-100"><div class="card-body p-4"><h2 class="h6 mb-3">Request context</h2><dl class="row small mb-0"><dt class="col-5 text-secondary">Status</dt><dd class="col-7 text-end">{{ ucfirst($log->status) }}</dd><dt class="col-5 text-secondary">User</dt><dd class="col-7 text-end">{{ $log->user?->email ?: 'Guest' }}</dd><dt class="col-5 text-secondary">IP</dt><dd class="col-7 text-end">{{ $log->ip_address ?: '—' }}</dd><dt class="col-5 text-secondary">Duration</dt><dd class="col-7 text-end">{{ $log->duration_ms !== null ? number_format($log->duration_ms).' ms' : '—' }}</dd></dl>@if($log->error_message)<div class="alert alert-danger small mt-3 mb-0">{{ $safeLogText($log->error_message) }}</div>@endif</div></div></div>
    <div class="col-xl-8">
        <div class="card content-card mb-4"><div class="card-header bg-white p-3"><h2 class="h6 mb-0">User/server input</h2></div><div class="card-body p-0"><pre class="travel-log-json mb-0">{{ $safeLogText($log->request_payload) }}</pre></div></div>
        <div class="card content-card"><div class="card-header bg-white p-3"><h2 class="h6 mb-0">Server response</h2></div><div class="card-body p-0"><pre class="travel-log-json mb-0">{{ $safeLogText($log->response_payload) }}</pre></div></div>
    </div>
</div>
@endsection

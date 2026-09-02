@extends('layouts.admin')

@section('title', $product === 'all' ? 'API Logs' : ucfirst($product).' Logs')

@section('content')
<header class="mb-4">
    <p class="text-danger fw-semibold mb-1">KAROSSY OPERATIONS</p>
    <h1 class="h3 fw-bold mb-2">{{ $product === 'all' ? 'API Logs' : ucfirst($product).' Operational Logs' }}</h1>
    <p class="text-secondary mb-0">Trace every safe request stage, response summary, user, IP address and processing time.</p>
</header>

<div class="card content-card">
    <div class="card-header bg-white border-0 p-3 p-md-4">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-4"><label class="form-label small">Stage</label><select class="form-select" name="stage"><option value="">All stages</option>@foreach(['search','revalidation','booking'] as $stage)<option value="{{ $stage }}" @selected(request('stage') === $stage)>{{ ucfirst($stage) }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label small">Status</label><select class="form-select" name="status"><option value="">All statuses</option><option value="success" @selected(request('status') === 'success')>Success</option><option value="failed" @selected(request('status') === 'failed')>Failed</option></select></div>
            <div class="col-md-4 d-flex gap-2"><button class="btn btn-karossy" type="submit"><i class="bi bi-funnel"></i> Apply filters</button><a class="btn btn-outline-secondary" href="{{ route('admin.travel-logs.index', $product) }}">Clear</a></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table admin-data-table align-middle mb-0">
            <thead><tr><th class="ps-4">Time</th><th>Product</th><th>Stage</th><th>User</th><th>IP address</th><th>Duration</th><th>Status</th><th class="text-end pe-4">Details</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="ps-4"><strong>{{ $log->created_at->format('d M Y') }}</strong><small class="d-block text-secondary">{{ $log->created_at->format('H:i:s') }}</small></td>
                    <td><span class="badge text-bg-light border text-uppercase">{{ $log->product_type }}</span></td>
                    <td>{{ str($log->stage)->replace('_', ' ')->headline() }}</td>
                    <td>@if($log->user)<strong>{{ $log->user->name }}</strong><small class="d-block text-secondary">{{ $log->user->email }}</small>@else<span class="text-secondary">Guest</span>@endif</td>
                    <td><code>{{ $log->ip_address ?: '—' }}</code></td>
                    <td>{{ $log->duration_ms !== null ? number_format($log->duration_ms).' ms' : '—' }}</td>
                    <td><span class="status-pill {{ $log->status === 'success' ? 'status-active' : 'status-inactive' }}">{{ ucfirst($log->status) }}</span></td>
                    <td class="text-end pe-4"><a class="table-action-btn" href="{{ route('admin.travel-logs.show', $log) }}"><i class="bi bi-eye"></i><span>View</span></a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-5 text-secondary"><i class="bi bi-journal-text d-block fs-2 mb-2"></i>No operational logs match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="card-footer bg-white border-0 p-3 p-md-4">{{ $logs->links() }}</div>@endif
</div>
@endsection

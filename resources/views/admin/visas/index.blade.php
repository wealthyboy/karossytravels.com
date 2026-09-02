@extends('layouts.admin')

@section('title', 'Visa services')

@section('content')
<header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <p class="text-danger fw-semibold mb-1">VISA OPERATIONS</p>
        <h1 class="h3 fw-bold mb-2">Visa services</h1>
        <p class="text-secondary mb-0">Manage passport-to-destination eligibility, requirements, processing, consultation and customer pricing.</p>
    </div>
    <a class="btn btn-karossy" href="{{ route('admin.visas.create') }}"><i class="bi bi-plus-lg me-2"></i>New visa service</a>
</header>

<div class="card content-card admin-table-card">
    <div class="table-responsive">
        <table class="table admin-data-table align-middle mb-0">
            <thead><tr><th class="ps-4">Route</th><th>Service</th><th>Processing</th><th>Customer fee</th><th>Status</th><th class="text-end pe-4">Actions</th></tr></thead>
            <tbody>
            @forelse($visas as $visa)
                <tr>
                    <td class="ps-4"><strong>{{ $visa->passport_country ?: 'Any passport' }} → {{ $visa->country }}</strong><small class="d-block text-secondary">{{ strtoupper($visa->passport_country_code ?: 'ANY') }} / {{ strtoupper($visa->destination_country_code ?: '—') }}</small></td>
                    <td><strong>{{ $visa->name ?: str($visa->visa_type)->headline() }}</strong><small class="d-block text-secondary">{{ str($visa->visa_type)->headline() }}</small></td>
                    <td>{{ $visa->processing_time ?: number_format($visa->duration_days).' days' }}</td>
                    <td>{{ $visa->currency ?: 'NGN' }} {{ number_format($visa->fee_cents / 100, 2) }}</td>
                    <td><span class="status-pill {{ $visa->active ? 'status-active' : 'status-inactive' }}">{{ $visa->active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-end pe-4"><div class="d-inline-flex gap-2"><a class="table-action-btn" href="{{ route('admin.visas.edit', $visa) }}"><i class="bi bi-pencil"></i><span>Edit</span></a><form method="POST" action="{{ route('admin.visas.destroy', $visa) }}" data-confirm="Delete this visa service?">@csrf @method('DELETE')<button class="table-action-btn text-danger" type="submit"><i class="bi bi-trash3"></i><span>Delete</span></button></form></div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-5"><i class="bi bi-passport d-block fs-2 mb-2"></i>No visa services have been added.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($visas->hasPages())<div class="card-footer bg-white border-0 p-3 p-md-4">{{ $visas->links() }}</div>@endif
</div>
@endsection

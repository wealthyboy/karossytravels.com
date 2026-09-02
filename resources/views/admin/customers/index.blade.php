@extends('layouts.admin')
@section('title', 'Customers')
@section('content')
@php
    $canManage = app()->isLocal() || auth()->user()?->hasPermission('customers.manage');
    $sortUrl = fn (string $column) => route('admin.customers.index', array_merge(request()->query(), ['sort' => $column, 'direction' => request('sort') === $column && request('direction') !== 'desc' ? 'desc' : 'asc']));
    $sortIcon = fn (string $column) => request('sort') === $column ? (request('direction') === 'desc' ? 'bi-sort-down' : 'bi-sort-up') : 'bi-arrow-down-up';
@endphp
<header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"><div><p class="text-danger fw-semibold mb-1">CUSTOMER MANAGEMENT</p><h1 class="h3 fw-bold mb-2">Customers</h1><p class="text-secondary mb-0">Profiles used for B2C, B2B and staff-assisted travel bookings.</p></div>@if($canManage)<a href="{{ route('admin.customers.create') }}" class="btn btn-karossy"><i class="bi bi-plus-lg me-2"></i>New customer</a>@endif</header>

<form id="customer-bulk-form" method="POST" action="{{ route('admin.customers.bulk-destroy') }}" onsubmit="return confirm('Delete the selected customers? Customers with bookings will be skipped.')">@csrf @method('DELETE')</form>
<div class="card content-card admin-table-card">
    <div class="card-header bg-white border-0 p-3 p-md-4"><div class="d-flex flex-column flex-xl-row justify-content-between gap-3"><form method="GET" class="d-flex flex-column flex-md-row gap-2 flex-grow-1" autocomplete="off"><div class="input-group admin-table-search"><span class="input-group-text"><i class="bi bi-search"></i></span><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, email, phone or company"></div><select name="status" class="form-select customer-filter"><option value="">All statuses</option>@foreach(['active'=>'Active','pending'=>'Pending','blocked'=>'Blocked'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select><button class="btn admin-search-button">Search</button>@if(request()->hasAny(['q','status']))<a href="{{ route('admin.customers.index') }}" class="btn btn-link text-secondary">Clear</a>@endif</form>@if($canManage)<button type="submit" form="customer-bulk-form" class="btn btn-outline-danger bulk-delete-button" disabled><i class="bi bi-trash3 me-2"></i>Delete selected <span class="selected-count"></span></button>@endif</div></div>
    <div class="table-responsive"><table class="table admin-data-table align-middle mb-0"><thead><tr><th class="table-check ps-4"><input class="form-check-input select-all-rows" type="checkbox" aria-label="Select all customers"></th><th><a href="{{ $sortUrl('first_name') }}">Customer <i class="bi {{ $sortIcon('first_name') }}"></i></a></th><th><a href="{{ $sortUrl('email') }}">Contact <i class="bi {{ $sortIcon('email') }}"></i></a></th><th><a href="{{ $sortUrl('status') }}">Status <i class="bi {{ $sortIcon('status') }}"></i></a></th><th>Bookings</th><th class="text-end pe-4">Actions</th></tr></thead><tbody>
    @forelse($customers as $customer)
        <tr>
            <td class="table-check ps-4"><input class="form-check-input row-checkbox" form="customer-bulk-form" name="ids[]" value="{{ $customer->id }}" type="checkbox" aria-label="Select {{ $customer->full_name }}"></td>
            <td><a class="customer-name-link" href="{{ route('admin.customers.show', $customer) }}"><strong>{{ $customer->full_name }}</strong></a>@if($customer->company_name)<small class="d-block text-secondary">{{ $customer->company_name }}</small>@endif</td>
            <td><span>{{ $customer->email }}</span>@if($customer->phone)<small class="d-block text-secondary">{{ $customer->phone }}</small>@endif</td>
            <td><span class="customer-status customer-status-{{ $customer->status }}"><span></span>{{ ucfirst($customer->status) }}</span></td>
            <td><span class="count-pill">{{ $customer->orders_count }}</span></td>
            <td class="text-end pe-4">
                @php
                    $canDelete = $canManage && $customer->orders_count === 0;
                @endphp
                @include('admin.partials.actions', [
                    'showUrl' => route('admin.customers.show', $customer),
                    'editUrl' => route('admin.customers.edit', $customer),
                    'deleteUrl' => route('admin.customers.destroy', $customer),
                    'canManage' => $canManage,
                    'canDelete' => $canDelete,
                ])
            </td>
        </tr>
    @empty<tr><td colspan="6" class="text-center text-secondary py-5"><i class="bi bi-people d-block fs-2 mb-2"></i>No customers found.</td></tr>@endforelse
    </tbody></table></div>
    <div class="card-footer bg-white border-0 p-3 p-md-4 d-flex justify-content-between align-items-center"><small class="text-secondary">Showing {{ $customers->firstItem() ?? 0 }}–{{ $customers->lastItem() ?? 0 }} of {{ $customers->total() }}</small>{{ $customers->links() }}</div>
</div>
@endsection

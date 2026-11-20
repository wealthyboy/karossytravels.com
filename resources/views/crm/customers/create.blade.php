@extends('layouts.crm')
@section('title', 'Add customer')
@section('topbar-title', 'Add customer')
@section('content')
<div class="crm-page-heading"><div><p class="crm-eyebrow">Relationships</p><h1>Add customer</h1><p>Create the traveller profile here before preparing a booking or payment request.</p></div><a class="crm-button crm-button-quiet" href="{{ route('crm.customers.index') }}"><i class="bi bi-arrow-left"></i> Customers</a></div>
@if($errors->any())<div class="alert alert-danger"><strong>Please correct the highlighted information.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('crm.customers.store') }}" class="crm-flight-quote-form">@csrf
<section class="crm-card crm-quote-panel"><h2>Customer profile</h2><p>Only the booking and communication details required by the CRM are collected here.</p><div class="crm-passenger-grid"><label>Title<select name="title"><option value="">Select</option>@foreach(['Mr','Mrs','Ms','Miss','Dr','Prof'] as $title)<option value="{{ $title }}" @selected(old('title') === $title)>{{ $title }}</option>@endforeach</select></label><label>First name<input name="first_name" value="{{ old('first_name') }}" required></label><label>Last name<input name="last_name" value="{{ old('last_name') }}" required></label><label>Email address<input type="email" name="email" value="{{ old('email') }}" required></label><label>Phone<input name="phone" value="{{ old('phone') }}"></label><label>Company (optional)<input name="company_name" value="{{ old('company_name') }}"></label><label>Nationality (2 letters)<input maxlength="2" name="nationality" value="{{ old('nationality', 'NG') }}"></label><label>Status<select name="status" required>@foreach(['active' => 'Active', 'pending' => 'Pending', 'blocked' => 'Blocked'] as $value => $label)<option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>@endforeach</select></label></div></section>
<button class="crm-button" type="submit"><i class="bi bi-person-check"></i> Create customer</button>
</form>
@endsection

@php($editing = isset($customer))
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card content-card"><div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start mb-4"><div><h2 class="h5 mb-1">Customer details</h2><p class="small text-secondary mb-0">Use names exactly as they appear on the traveller's document.</p></div><span class="badge badge-system rounded-pill">Required for booking</span></div>
            <div class="row g-3">
                <div class="col-md-2"><label class="form-label">Title</label><select name="title" class="form-select"><option value="">—</option>@foreach(['Mr','Mrs','Ms','Miss','Dr','Prof'] as $title)<option @selected(old('title', $customer->title ?? '') === $title)>{{ $title }}</option>@endforeach</select></div>
                <div class="col-md-5"><label class="form-label">First name</label><input name="first_name" class="form-control" value="{{ old('first_name', $customer->first_name ?? '') }}" required></div>
                <div class="col-md-4"><label class="form-label">Middle name</label><input name="middle_name" class="form-control" value="{{ old('middle_name', $customer->middle_name ?? '') }}"></div>
                <div class="col-md-4"><label class="form-label">Last name</label><input name="last_name" class="form-control" value="{{ old('last_name', $customer->last_name ?? '') }}" required></div>
                <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $customer->email ?? '') }}" required></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control" value="{{ old('phone', $customer->phone ?? '') }}"></div>
                <div class="col-md-4"><label class="form-label">Date of birth</label><input type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', isset($customer) && $customer->date_of_birth ? $customer->date_of_birth->toDateString() : '') }}" max="{{ now()->subDay()->toDateString() }}"></div>
                <div class="col-md-4"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="">Not specified</option>@foreach(['male'=>'Male','female'=>'Female','unspecified'=>'Prefer not to say'] as $value=>$label)<option value="{{ $value }}" @selected(old('gender', $customer->gender ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Nationality code</label><input name="nationality" class="form-control text-uppercase" maxlength="2" placeholder="NG" value="{{ old('nationality', $customer->nationality ?? '') }}"></div>
                <div class="col-md-4"><label class="form-label">Country code</label><input name="country" class="form-control text-uppercase" maxlength="2" placeholder="NG" value="{{ old('country', $customer->country ?? '') }}"></div>
                <div class="col-md-6"><label class="form-label">Company name</label><input name="company_name" class="form-control" value="{{ old('company_name', $customer->company_name ?? '') }}"><div class="form-text">Optional, for customers travelling on behalf of a company.</div></div>
                <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select" required>@foreach(['active'=>'Active','pending'=>'Pending','blocked'=>'Blocked'] as $value=>$label)<option value="{{ $value }}" @selected(old('status', $customer->status ?? 'active') === $value)>{{ $label }}</option>@endforeach</select></div>
            </div>
        </div></div>
    </div>
    <div class="col-xl-4">
        <div class="card content-card mb-4"><div class="card-body p-4"><h2 class="h5 mb-1">Travel document</h2><p class="small text-secondary mb-3">Passport numbers are encrypted at rest.</p>
            <div class="mb-3"><label class="form-label">Passport number</label><input name="passport_number" class="form-control" value="" placeholder="{{ $editing && $customer->passport_number ? 'Leave blank to keep existing' : '' }}" autocomplete="off"></div>
            <div class="row g-3"><div class="col-5"><label class="form-label">Issuer</label><input name="passport_country" class="form-control text-uppercase" maxlength="2" placeholder="NG" value="{{ old('passport_country', $customer->passport_country ?? '') }}"></div><div class="col-7"><label class="form-label">Expiry date</label><input type="date" name="passport_expires_at" class="form-control" min="{{ now()->addDay()->toDateString() }}" value="{{ old('passport_expires_at', isset($customer) && $customer->passport_expires_at ? $customer->passport_expires_at->toDateString() : '') }}"></div></div>
        </div></div>
        <div class="card content-card"><div class="card-body p-4"><label class="form-label">Internal notes</label><textarea name="notes" class="form-control" rows="5" placeholder="Visible only to authorised staff">{{ old('notes', $customer->notes ?? '') }}</textarea></div></div>
    </div>
</div>
<div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ $editing ? route('admin.customers.show', $customer) : route('admin.customers.index') }}" class="btn btn-light">Cancel</a><button class="btn btn-karossy" type="submit"><i class="bi bi-check-lg me-2"></i>{{ $editing ? 'Save changes' : 'Create customer' }}</button></div>

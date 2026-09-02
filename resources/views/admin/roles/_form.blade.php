@php
    $selectedPermissionIds = collect(old('permission_ids', isset($role) ? $role->permissions->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
    $isSuperAdmin = isset($role) && $role->name === 'super-admin';
@endphp
<div class="row g-4 mb-4">
    <div class="col-md-6"><label for="label" class="form-label fw-semibold">Role name</label><input id="label" name="label" value="{{ old('label', $role->label ?? '') }}" class="form-control @error('label') is-invalid @enderror" required maxlength="100" placeholder="Support Agent">@error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-text">The name shown to administrators.</div></div>
    <div class="col-md-6"><label for="name" class="form-label fw-semibold">Role key</label><input id="name" name="name" value="{{ old('name', $role->name ?? '') }}" class="form-control font-monospace @error('name') is-invalid @enderror" required maxlength="100" placeholder="support-agent" @disabled($isSystem ?? false)>@if($isSystem ?? false)<input type="hidden" name="name" value="{{ $role->name }}">@endif @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-text">Use a lowercase slug. Built-in role keys are locked.</div></div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-1">Permissions</h2><p class="small text-secondary mb-0">Choose what users with this role can access and manage.</p></div>@unless($isSuperAdmin)<button type="button" class="btn btn-sm btn-outline-secondary" data-permission-toggle>Select all</button>@endunless</div>
@if($isSuperAdmin)<div class="alert alert-info small"><i class="bi bi-info-circle-fill me-2"></i>The Super Administrator role always receives every permission.</div>@endif
@error('permission_ids.*')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
<div class="row g-3 permission-grid">
    @forelse($permissionGroups as $group => $permissions)
        <div class="col-md-6 col-xl-4"><fieldset class="border rounded-3 p-3 h-100"><legend class="float-none w-auto px-2 fs-6 fw-semibold mb-1">{{ $group }}</legend>
            @foreach($permissions as $permission)<div class="form-check py-1"><input class="form-check-input permission-checkbox" type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" id="permission-{{ $permission->id }}" @checked($isSuperAdmin || $selectedPermissionIds->contains($permission->id)) @disabled($isSuperAdmin)><label class="form-check-label" for="permission-{{ $permission->id }}"><span class="d-block">{{ $permission->label }}</span><code class="small">{{ $permission->name }}</code></label></div>@endforeach
        </fieldset></div>
    @empty
        <div class="col-12"><div class="alert alert-warning mb-0">Create permissions before configuring a role.</div></div>
    @endforelse
</div>

@push('scripts')
<script>
document.querySelector('[data-permission-toggle]')?.addEventListener('click', function () {
    const boxes = [...document.querySelectorAll('.permission-checkbox:not(:disabled)')];
    const selectAll = boxes.some(box => !box.checked);
    boxes.forEach(box => box.checked = selectAll);
    this.textContent = selectAll ? 'Clear all' : 'Select all';
});
</script>
@endpush

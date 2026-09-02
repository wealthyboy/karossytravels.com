<div class="mb-4">
    <label for="label" class="form-label fw-semibold">Display label</label>
    <input id="label" name="label" value="{{ old('label', $permission->label ?? '') }}" class="form-control @error('label') is-invalid @enderror" required maxlength="100" placeholder="Export reports">
    @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">A clear name administrators will understand.</div>
</div>
<div class="mb-4">
    <label for="name" class="form-label fw-semibold">Permission key</label>
    <input id="name" name="name" value="{{ old('name', $permission->name ?? '') }}" class="form-control font-monospace @error('name') is-invalid @enderror" required maxlength="100" placeholder="reports.export" @disabled($isSystem ?? false)>
    @if ($isSystem ?? false)<input type="hidden" name="name" value="{{ $permission->name }}">@endif
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">Use lowercase <code>resource.action</code> format. Built-in keys are locked because application code depends on them.</div>
</div>

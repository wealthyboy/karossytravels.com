@props(['name', 'label', 'type' => 'text', 'required' => false, 'hint' => null, 'full' => false, 'value' => null])
<div class="{{ $full ? 'enquiry-full' : '' }}" data-enquiry-field="{{ $name }}">
    <label class="form-label" for="enquiry_{{ $name }}">{{ $label }}@unless($required)<span class="enquiry-optional"> (optional)</span>@endunless</label>
    @if($type === 'textarea')
        <textarea id="enquiry_{{ $name }}" name="{{ $name }}" {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }} rows="4" @required($required)>{{ old($name, $value) }}</textarea>
    @else
        <input id="enquiry_{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }} @required($required)>
    @endif
    @if($hint)<p class="form-text">{{ $hint }}</p>@endif
    <p class="enquiry-field-error text-danger small mb-0" data-enquiry-field-error @if(!$errors->has($name)) hidden @endif>{{ $errors->first($name) }}</p>
</div>

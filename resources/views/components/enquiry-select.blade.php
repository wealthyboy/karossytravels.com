@props(['name', 'label', 'options', 'placeholder' => 'Select an option', 'full' => false, 'hint' => null, 'value' => null])
<div class="{{ $full ? 'enquiry-full' : '' }}" data-enquiry-field="{{ $name }}">
    <label class="form-label" for="enquiry_{{ $name }}">{{ $label }}</label>
    @if($attributes->has('data-searchable-select'))<label class="enquiry-select-wrap">@endif
    <select id="enquiry_{{ $name }}" name="{{ $name }}" aria-label="{{ $label }}" {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }} required>
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $option)<option value="{{ $option }}" @selected(old($name, $value) === $option)>{{ $option }}</option>@endforeach
    </select>
    @if($attributes->has('data-searchable-select'))</label>@endif
    @if($hint)<p class="form-text">{{ $hint }}</p>@endif
    <p class="enquiry-field-error text-danger small mb-0" data-enquiry-field-error @if(!$errors->has($name)) hidden @endif>{{ $errors->first($name) }}</p>
</div>

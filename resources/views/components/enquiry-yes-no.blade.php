@props(['name', 'label', 'full' => false, 'hint' => null])
<fieldset class="{{ $full ? 'enquiry-full' : '' }}" data-enquiry-field="{{ $name }}">
    <legend class="form-label" @if($name === 'supporting_document') data-visa-document-label @endif>{{ $label }}</legend>
    <div class="enquiry-radio-options">
        @foreach(['Yes', 'No'] as $answer)<label><input class="form-check-input" type="radio" name="{{ $name }}" value="{{ $answer }}" @checked(old($name) === $answer) required> {{ $answer }}</label>@endforeach
    </div>
    {{ $slot }}
    @if($hint)<p class="form-text" @if($name === 'supporting_document') data-visa-document-hint @endif>{{ $hint }}</p>@endif
    <p class="enquiry-field-error text-danger small mb-0" data-enquiry-field-error @if(!$errors->has($name)) hidden @endif>{{ $errors->first($name) }}</p>
</fieldset>

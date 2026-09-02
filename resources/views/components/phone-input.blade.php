@props(['name' => 'phone', 'codeName' => 'phone_code', 'value' => '', 'selectedCode' => '+234', 'required' => true])
@php
    $oldKey = str_replace(['][', '[', ']'], ['.', '.', ''], $name);
    $oldCodeKey = str_replace(['][', '[', ']'], ['.', '.', ''], $codeName);
@endphp
<div class="checkout-phone-control">
    <select class="checkout-phone-code" name="{{ $codeName }}" aria-label="Phone country code" @required($required)>
        @foreach(\App\Support\PhoneCountryCodes::options() as $option)
            <option value="{{ $option['dial'] }}" @selected(old($oldCodeKey, $selectedCode) === $option['dial'])>{{ $option['flag'] }} {{ $option['dial'] }}</option>
        @endforeach
    </select>
    <input {{ $attributes->class(['form-control']) }} name="{{ $name }}" value="{{ old($oldKey, $value) }}" autocomplete="tel" inputmode="tel" placeholder="800 000 0000" @required($required)>
</div>

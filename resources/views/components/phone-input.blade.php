@props(['name' => 'phone', 'codeName' => 'phone_code', 'value' => '', 'selectedCode' => '+234', 'required' => true])
@php
    $oldKey = str_replace(['][', '[', ']'], ['.', '.', ''], $name);
    $oldCodeKey = str_replace(['][', '[', ']'], ['.', '.', ''], $codeName);
    $phoneInput = \App\Support\PhoneCountryCodes::splitForInput(
        old($oldKey, $value),
        old($oldCodeKey, $selectedCode),
    );
@endphp
<div class="checkout-phone-control">
    <select class="checkout-phone-code" name="{{ $codeName }}" aria-label="Phone country code" @required($required)>
        @foreach(\App\Support\PhoneCountryCodes::options() as $option)
            <option value="{{ $option['dial'] }}" @selected($phoneInput['code'] === $option['dial'])>{{ $option['flag'] }} {{ $option['dial'] }}</option>
        @endforeach
    </select>
    <input {{ $attributes->class(['form-control']) }} name="{{ $name }}" value="{{ $phoneInput['number'] }}" autocomplete="tel" inputmode="tel" placeholder="800 000 0000" @required($required)>
</div>

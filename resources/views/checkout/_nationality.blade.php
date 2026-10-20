<div class="col-md-4">
    <label class="checkout-nationality d-block">
        <span class="form-label d-block">Nationality</span>
        <select name="travellers[{{ $index }}][nationality]" class="form-select @error("travellers.$index.nationality") is-invalid @enderror" required aria-label="Nationality" data-nationality-code="{{ $index }}" data-searchable-select data-search-placeholder="Search nationality">
            <option value="">Select nationality</option>
            @foreach(\App\Support\CountryOptions::all() as $code => $country)
                <option value="{{ $code }}" @selected(old("travellers.$index.nationality", 'NG') === $code)>{{ $country }}</option>
            @endforeach
        </select>
    </label>
    @if($requiresPassport ?? true)
        <input type="hidden" name="travellers[{{ $index }}][passport_country]" value="{{ old("travellers.$index.passport_country", old("travellers.$index.nationality", 'NG')) }}" data-passport-country="{{ $index }}">
    @endif
</div>

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCurrencySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currencies' => ['required', 'array'],
            'currencies.*' => ['array'],
            'currencies.*.manual_rate' => ['nullable', 'numeric', 'gt:0'],
            'currencies.*.adjustment_type' => ['required', Rule::in(['none', 'markup', 'markdown'])],
            'currencies.*.adjustment_mode' => ['required', Rule::in(['percentage', 'fixed'])],
            'currencies.*.adjustment_percent' => ['nullable', 'numeric', 'min:0', 'max:9999.9999'],
            'currencies.*.enabled' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $currencies = collect($this->input('currencies', []))->map(function (array $currency): array {
            $currency['adjustment_mode'] ??= 'percentage';

            return $currency;
        })->all();

        $this->merge(['currencies' => $currencies]);
    }

    public function after(): array
    {
        return [function ($validator): void {
            foreach ($this->input('currencies', []) as $code => $currency) {
                if (($currency['adjustment_mode'] ?? 'percentage') === 'percentage' && (float) ($currency['adjustment_percent'] ?? 0) > 100) {
                    $validator->errors()->add("currencies.$code.adjustment_percent", 'A percentage adjustment cannot exceed 100%.');
                }
            }
        }];
    }
}

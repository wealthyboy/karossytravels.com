<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePricingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'adjustment_type' => ['required', Rule::in(['markup', 'markdown'])],
            'markup_type' => ['sometimes', Rule::in(['percentage'])],
            'markup_value' => ['nullable', 'numeric', 'min:0', 'max:'.($this->input('adjustment_type') === 'markdown' ? '99.99' : '1000000')],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'markup_type.in' => 'Only percentage pricing is supported. Fixed amounts are no longer available.',
            'markup_value.max' => $this->input('adjustment_type') === 'markdown'
                ? 'A markdown must be less than 100%. Enter a percentage from 0 to 99.99.'
                : 'The markup percentage is too large.',
        ];
    }
}

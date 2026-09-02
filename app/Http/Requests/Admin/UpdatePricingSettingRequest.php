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
            'markup_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'markup_value' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'currency' => ['required', Rule::in(['USD', 'NGN'])],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}

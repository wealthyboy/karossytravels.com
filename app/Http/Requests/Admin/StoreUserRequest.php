<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app()->isLocal() || $this->user()?->hasPermission('team.manage') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'account_type' => ['required', Rule::in(['admin', 'b2c', 'b2b'])],
            'company_name' => ['nullable', 'required_if:account_type,b2b', 'string', 'max:180'],
            'currency_code' => ['required', Rule::in(['NGN', 'USD', 'GBP', 'EUR', 'CAD', 'ZAR', 'AED'])],
            'status' => ['required', Rule::in(['active', 'suspended', 'pending'])],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'distinct', 'exists:roles,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'currency_code' => strtoupper((string) $this->input('currency_code', 'NGN')),
        ]);
    }
}

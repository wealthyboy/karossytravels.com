<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['nullable', Rule::in(['Mr', 'Mrs', 'Ms', 'Miss', 'Dr', 'Prof'])],
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('customers', 'email')->ignore($this->route('customer'))],
            'phone' => ['nullable', 'string', 'max:40'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'unspecified'])],
            'nationality' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'country' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'company_name' => ['nullable', 'string', 'max:180'],
            'status' => ['required', Rule::in(['active', 'pending', 'blocked'])],
            'passport_number' => ['nullable', 'string', 'max:80'],
            'passport_country' => ['nullable', 'required_with:passport_number', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'passport_expires_at' => ['nullable', 'required_with:passport_number', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'nationality' => strtoupper((string) $this->input('nationality')) ?: null,
            'country' => strtoupper((string) $this->input('country')) ?: null,
            'passport_country' => strtoupper((string) $this->input('passport_country')) ?: null,
        ]);
    }
}

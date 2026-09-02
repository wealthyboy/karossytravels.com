<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateFlightOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $travellers = collect($this->input('travellers', []))->map(function ($traveller): array {
            $traveller = is_array($traveller) ? $traveller : [];

            foreach (['first_name', 'last_name'] as $key) {
                if (isset($traveller[$key])) {
                    $traveller[$key] = preg_replace('/\\s+/u', ' ', trim((string) $traveller[$key]));
                }
            }
            foreach (['nationality', 'passport_country', 'passport_number'] as $key) {
                if (isset($traveller[$key])) {
                    $traveller[$key] = strtoupper(trim((string) $traveller[$key]));
                }
            }
            if (isset($traveller['gender'])) {
                $traveller['gender'] = strtolower((string) $traveller['gender']);
            }

            return $traveller;
        })->values()->all();

        $this->merge([
            'travellers' => $travellers,
            'operator_markup_type' => $this->input('operator_markup_type', 'none'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'travellers' => ['required', 'array', 'min:1', 'max:9'],
            'travellers.*.type' => ['required', Rule::in(['ADT', 'CNN', 'INF'])],
            'travellers.*.title' => ['required', Rule::in(['Mr', 'Mrs', 'Ms', 'Miss', 'Dr'])],
            'travellers.*.first_name' => ['required', 'string', 'max:80', "regex:/^[\\pL][\\pL\\pM'’\\-]*(?: [\\pL][\\pL\\pM'’\\-]*)*$/u"],
            'travellers.*.last_name' => ['required', 'string', 'max:80', "regex:/^[\\pL][\\pL\\pM'’\\-]*(?: [\\pL][\\pL\\pM'’\\-]*)*$/u"],
            'travellers.*.date_of_birth' => ['required', 'date', 'before:today'],
            'travellers.*.gender' => ['required', Rule::in(['male', 'female', 'unspecified'])],
            'travellers.*.nationality' => ['required', 'string', 'size:2'],
            'travellers.*.passport_number' => ['required', 'string', 'max:30'],
            'travellers.*.passport_country' => ['required', 'string', 'size:2'],
            'travellers.*.passport_expiry' => ['required', 'date', 'after:today'],
            'agency_number' => ['nullable', 'string', 'max:10', 'regex:/^[0-9A-Z]{6}([1-9A-Z\*]{1}|[0-9A-Z]{4})?$/i'],
            'addons' => ['nullable', 'array', 'max:20'],
            'addons.*' => ['uuid', 'distinct', Rule::exists('addons', 'id')->where(fn ($query) => $query->where('type', 'flight')->where('active', true))],
            'operator_markup_type' => ['required', Rule::in(['none', 'fixed', 'percentage'])],
            'operator_markup_value' => ['nullable', 'numeric', 'min:0', 'max:100000000', 'required_unless:operator_markup_type,none'],
        ];
    }

    public function messages(): array
    {
        return [
            'travellers.*.first_name.regex' => 'First names may contain letters, spaces, apostrophes and hyphens only.',
            'travellers.*.last_name.regex' => 'Last names may contain letters, spaces, apostrophes and hyphens only.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Support\PhoneCountryCodes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreFlightTravellersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'travellers' => ['required', 'array', 'min:1', 'max:9'],
            'travellers.*.type' => ['required', Rule::in(['ADT', 'CNN', 'INF'])],
            'travellers.*.title' => ['required', Rule::in(['Mr', 'Mrs', 'Ms', 'Miss', 'Dr'])],
            'travellers.*.first_name' => ['required', 'string', 'max:80', "regex:/^[\\pL][\\pL\\pM'’\\-]*(?: [\\pL][\\pL\\pM'’\\-]*)*$/u"],
            'travellers.*.last_name' => ['required', 'string', 'max:80', "regex:/^[\\pL][\\pL\\pM'’\\-]*(?: [\\pL][\\pL\\pM'’\\-]*)*$/u"],
            'travellers.*.date_of_birth' => ['required', 'date', 'before:today'],
            'travellers.*.gender' => ['required', Rule::in(['male', 'female', 'unspecified'])],
            'travellers.*.nationality' => ['required', 'string', 'size:2'],
            'travellers.*.passport_number' => ['required', 'alpha_num', 'min:5', 'max:30'],
            'travellers.*.passport_country' => ['required', 'string', 'size:2'],
            'travellers.*.passport_expiry' => ['required', 'date', 'after:today'],
            'contact.email' => ['required', 'email:rfc', 'max:255'],
            'contact.phone_code' => ['required', 'string', 'regex:/^\+[0-9]{1,4}$/'],
            'contact.phone' => ['required', 'string', 'regex:/^[0-9 +()-]{7,24}$/'],
            'notifications' => ['nullable', 'boolean'],
        ];
    }

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

        $contact = is_array($this->input('contact')) ? $this->input('contact') : [];
        $contact['phone_code'] = $contact['phone_code'] ?? '+234';
        $contact['phone'] = PhoneCountryCodes::normalize($contact['phone_code'], $contact['phone'] ?? '');

        $this->merge(['travellers' => $travellers, 'contact' => $contact]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('travellers', []) as $index => $traveller) {
                if (! is_array($traveller) || ($traveller['type'] ?? null) !== 'ADT' || empty($traveller['date_of_birth'])) {
                    continue;
                }

                try {
                    $dateOfBirth = CarbonImmutable::parse((string) $traveller['date_of_birth'])->startOfDay();
                } catch (\Throwable) {
                    continue;
                }

                if ($dateOfBirth->greaterThan(CarbonImmutable::today()->subYears(18))) {
                    $validator->errors()->add(
                        "travellers.{$index}.date_of_birth",
                        'Adult travellers must be at least 18 years old on the booking date.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'travellers.*.date_of_birth.required' => 'Enter the traveller\'s date of birth.',
            'travellers.*.date_of_birth.date' => 'Enter a valid date of birth.',
            'travellers.*.date_of_birth.before' => 'The date of birth must be before today.',
            'travellers.*.first_name.regex' => 'First names may contain letters, spaces, apostrophes and hyphens only.',
            'travellers.*.last_name.regex' => 'Last names may contain letters, spaces, apostrophes and hyphens only.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Travel\Pricing\DisplayCurrencyResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class HotelSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'destination_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'destination_label' => ['required', 'string', 'max:160'],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:16'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:8'],
            'children_ages' => [Rule::requiredIf(fn () => (int) $this->input('children', 0) > 0), 'array', 'list', 'size:'.(int) $this->input('children', 0)],
            'children_ages.*' => ['required', 'integer', 'min:0', 'max:17', function ($attribute, $value, $fail): void {
                if (is_bool($value)) {
                    $fail('Select a numeric age for every child.');
                }
            }],
            'rooms' => ['required', 'integer', 'min:1', 'max:8', 'lte:adults'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'session_id' => ['required', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'destination_code' => strtoupper((string) $this->input('destination_code')),
            'destination_label' => trim((string) $this->input('destination_label', $this->input('destination'))),
            'children' => $this->input('children', 0),
            'children_ages' => $this->input('children_ages', []),
            'currency' => app(DisplayCurrencyResolver::class)->resolve($this),
        ]);
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $rooms = (int) $this->input('rooms');
            if ((int) $this->input('adults') % $rooms !== 0 || (int) $this->input('children', 0) % $rooms !== 0) {
                $validator->errors()->add('rooms', 'Sabre requires the same adult and child counts in each room. For different room occupancies, search rooms separately.');
            }
        }];
    }

    public function messages(): array
    {
        return ['children_ages.required' => 'Select an age for every child.', 'children_ages.size' => 'Select exactly one age for every child.', 'children_ages.*.required' => 'Select an age for every child.'];
    }
}

<?php

namespace App\Http\Requests;

use App\Support\EnquiryOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CarHireEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $needsReturn = in_array($this->input('hire_type'), EnquiryOptions::RETURN_REQUIRED, true);

        return [
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'max:40'],
            'pickup_location' => ['required', 'string', 'max:500'],
            'dropoff_location' => ['required', 'string', 'max:500'],
            'pickup_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'return_end_date' => [Rule::requiredIf($needsReturn || $this->filled('return_end_time')), 'nullable', 'date_format:Y-m-d', 'after_or_equal:pickup_date'],
            'return_end_time' => [Rule::requiredIf($needsReturn || $this->filled('return_end_date')), 'nullable', 'date_format:H:i'],
            'hire_type' => ['required', Rule::in(EnquiryOptions::HIRE_TYPES)],
            'vehicle_preference' => ['required', Rule::in(EnquiryOptions::VEHICLES)],
            'passengers' => ['required', 'integer', 'min:1', 'max:100'],
            'luggage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'additional_requirements' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('return_end_date')) {
                return;
            }
            if ($this->input('return_end_date').' '.$this->input('return_end_time') <= $this->input('pickup_date').' '.$this->input('pickup_time')) {
                $validator->errors()->add('return_end_time', 'The return or end time must be after pick-up.');
            }
        }];
    }
}

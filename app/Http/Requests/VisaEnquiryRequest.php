<?php

namespace App\Http\Requests;

use App\Support\CountryOptions;
use App\Support\EnquiryOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class VisaEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $countries = array_values(CountryOptions::all());
        $yesNo = ['required', Rule::in(['Yes', 'No'])];
        $purpose = $this->input('purpose_of_travel');

        return [
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone_whatsapp' => ['required', 'string', 'max:40'],
            'nationality' => ['required', Rule::in($countries)],
            'country_of_residence' => ['required', Rule::in($countries)],
            'destination_country' => ['required', Rule::in($countries)],
            'purpose_of_travel' => ['required', 'string', Rule::in(array_keys(EnquiryOptions::VISA_TYPES))],
            'visa_type' => ['required', Rule::in(EnquiryOptions::visaTypes(is_string($purpose) ? $purpose : ''))],
            'proposed_travel_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'duration_number' => ['required', 'integer', 'min:1', 'max:999'],
            'duration_unit' => ['required', Rule::in(['Days', 'Weeks', 'Months'])],
            'number_of_applicants' => ['required', 'integer', 'min:1', 'max:50'],
            'occupation_status' => ['required', Rule::in(EnquiryOptions::OCCUPATIONS)],
            'previous_travel' => $yesNo,
            'countries_previously_visited' => ['exclude_unless:previous_travel,Yes', 'nullable', 'string', 'max:1000'],
            'previous_refusal' => $yesNo,
            'refusal_details' => ['exclude_unless:previous_refusal,Yes', 'nullable', 'string', 'max:1000'],
            'valid_passport' => $yesNo, 'holds_valid_visa' => $yesNo, 'supporting_document' => $yesNo,
            'application_timeline' => ['required', Rule::in(EnquiryOptions::TIMELINES)],
            'additional_information' => ['nullable', 'string', 'max:3000'],
        ];
    }
}

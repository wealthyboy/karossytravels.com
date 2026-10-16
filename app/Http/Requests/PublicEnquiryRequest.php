<?php

namespace App\Http\Requests;

use App\Support\EnquiryForms;
use App\Support\SocialLinks;
use Illuminate\Foundation\Http\FormRequest;

final class PublicEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return array_key_exists((string) $this->route('enquiry_type'), EnquiryForms::pages());
    }

    public function rules(): array
    {
        $rules = [
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9][0-9\s().-]{4,39}$/'],
            'message' => ['required', 'string', 'max:5000'],
            'website' => ['nullable', 'string', 'max:0'],
        ];
        foreach (EnquiryForms::fields((string) $this->route('enquiry_type')) as $name => [$label, $required]) {
            $rules[$name] = [$required ? 'required' : 'nullable', 'string', 'max:'.($name === 'cv_url' ? 2048 : 200)];
            if ($name === 'cv_url') {
                $rules[$name][] = 'url:http,https';
                $rules[$name][] = fn ($attribute, $value, $fail) => SocialLinks::isSafeUrl($value) ?: $fail('Use a full http:// or https:// URL without embedded credentials.');
            }
        }

        return $rules;
    }
}

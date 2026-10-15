<?php

namespace App\Http\Requests\Admin;

use App\Support\SocialLinks;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateGeneralSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The settings.manage permission is enforced on both admin routes.
    }

    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:254'],
            'phone_numbers' => ['nullable', 'array', 'max:10'],
            'phone_numbers.*' => ['required', 'string', 'max:40', 'regex:/^\+?[0-9][0-9\s().-]{4,39}$/'],
            'whatsapp_number' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9][0-9\s().-]{4,39}$/'],
            'social_links' => ['nullable', 'array:'.implode(',', array_keys(SocialLinks::platforms()))],
            'social_links.*' => ['nullable', 'string', 'max:2048', 'url:http,https', function ($attribute, $value, $fail): void {
                if (! SocialLinks::isSafeUrl($value)) {
                    $fail('Use a full http:// or https:// profile URL without a username/password in the address.');
                }
            }],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phones = $this->input('phone_numbers');
        if (is_string($phones)) {
            $phones = array_values(array_filter(array_map('trim', preg_split('/\R/u', $phones) ?: []), fn (string $phone): bool => $phone !== ''));
        }
        $links = $this->input('social_links');
        if (is_array($links)) {
            $links = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $links);
        }
        $this->merge(['phone_numbers' => $phones, 'social_links' => $links]);
    }

    public function attributes(): array
    {
        $attributes = ['site_name' => 'site name', 'contact_email' => 'contact email', 'phone_numbers.*' => 'phone number'];
        foreach (SocialLinks::platforms() as $key => $platform) {
            $attributes['social_links.'.$key] = $platform['label'].' profile link';
        }

        return $attributes;
    }
}

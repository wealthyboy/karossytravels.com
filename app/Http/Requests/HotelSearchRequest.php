<?php

namespace App\Http\Requests;

use App\Travel\Pricing\DisplayCurrencyResolver;
use Illuminate\Foundation\Http\FormRequest;

final class HotelSearchRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'destination_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'destination_label' => ['required', 'string', 'max:160'],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:16'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:8'],
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
            'currency' => app(DisplayCurrencyResolver::class)->resolve($this),
        ]);
    }
}

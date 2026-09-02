<?php

namespace App\Http\Requests;

use App\Travel\Pricing\DisplayCurrencyResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FlightSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'origin' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'destination' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'departure_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'return_date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:departure_date',
                Rule::requiredIf(fn (): bool => $this->input('trip_type') === 'round_trip'),
            ],
            'trip_type' => ['required', Rule::in(['one_way', 'round_trip', 'multi_city'])],
            'segments' => ['exclude_unless:trip_type,multi_city', 'required', 'array', 'min:2', 'max:6'],
            'segments.*.origin' => ['required_with:segments', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'segments.*.destination' => ['required_with:segments', 'string', 'size:3', 'different:segments.*.origin', 'regex:/^[A-Za-z]{3}$/'],
            'segments.*.departure_date' => ['required_with:segments', 'date_format:Y-m-d', 'after_or_equal:today'],
            'cabin' => ['required', Rule::in(['economy', 'premium_economy', 'business', 'first'])],
            'adults' => ['required', 'integer', 'min:1', 'max:9'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:8'],
            'infants' => ['sometimes', 'integer', 'min:0', 'max:4', 'lte:adults'],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'session_id' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, mixed> */
    protected function prepareForValidation(): array
    {
        $this->merge([
            'origin' => strtoupper((string) $this->input('origin')),
            'destination' => strtoupper((string) $this->input('destination')),
            'currency' => strtoupper((string) $this->input('currency', app(DisplayCurrencyResolver::class)->resolve($this))),
            'children' => $this->input('children', 0),
            'infants' => $this->input('infants', 0),
            'segments' => collect($this->input('segments', []))->map(fn (array $segment): array => [
                ...$segment,
                'origin' => strtoupper((string) ($segment['origin'] ?? '')),
                'destination' => strtoupper((string) ($segment['destination'] ?? '')),
            ])->all(),
        ]);

        return [];
    }
}

<?php

namespace App\Travel\Pricing;

use App\Models\Airline;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class FlightPromotionInput
{
    /** Shared validation for standalone deals and homepage promotions. */
    public function validate(Request $request): array
    {
        $data = $request->validate([
            'airline_scope' => ['required', Rule::in(['selected', 'all'])],
            'airline_ids' => ['exclude_if:airline_scope,all', 'required_if:airline_scope,selected', 'array', 'min:1'],
            'airline_ids.*' => ['integer', 'distinct', Rule::exists('airlines', 'id')->where('active', true)],
            'discount_percent' => ['required', 'numeric', 'min:0.01', 'max:99.99'],
            'booking_from' => ['nullable', 'required_with:booking_until', 'date_format:Y-m-d'],
            'booking_until' => ['nullable', 'required_with:booking_from', 'date_format:Y-m-d', 'after_or_equal:booking_from'],
            'travel_from' => ['nullable', 'required_without:booking_from', 'required_with:travel_until', 'date_format:Y-m-d'],
            'travel_until' => ['nullable', 'required_with:travel_from', 'date_format:Y-m-d', 'after_or_equal:travel_from'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [
            'airline_ids.required_if' => 'Choose at least one discounted airline, or select all airlines.',
            'travel_from.required_without' => 'Set a promotion booking window, a departure window, or both.',
        ]);
        $all = $data['airline_scope'] === 'all';
        $airlines = $all ? collect() : Airline::whereIn('id', $data['airline_ids'])->orderBy('id')->get();

        return [
            'airline_id' => $airlines->first()?->id,
            'airline_codes' => $airlines->pluck('code')->all(),
            'all_airlines' => $all,
            'discount_type' => 'percentage',
            'discount_value' => $data['discount_percent'],
            'discount_currency' => null,
            'booking_from' => $data['booking_from'] ?? null,
            'booking_until' => $data['booking_until'] ?? null,
            'travel_from' => $data['travel_from'] ?? null,
            'travel_until' => $data['travel_until'] ?? null,
            'priority' => $data['priority'] ?? 0,
        ];
    }
}

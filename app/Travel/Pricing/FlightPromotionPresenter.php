<?php

namespace App\Travel\Pricing;

use App\Models\FlightDeal;

final class FlightPromotionPresenter
{
    public function present(?int $id, int $discountMinor): ?array
    {
        if (! $id || $discountMinor <= 0 || ! ($deal = FlightDeal::find($id))) {
            return null;
        }

        return [
            'id' => $deal->id,
            'type' => $deal->discount_type,
            'percent' => $deal->discount_type === 'percentage' ? (float) $deal->discount_value : null,
            'discount_minor' => $discountMinor,
            'airlines_scope' => $deal->all_airlines ? 'all' : 'selected',
            'eligible_airline_codes' => array_values($deal->airline_codes ?? array_filter([$deal->airline?->code])),
            'booking_from' => $deal->booking_from?->toDateString(),
            'booking_until' => $deal->booking_until?->toDateString(),
            'travel_from' => $deal->travel_from?->toDateString(),
            'travel_until' => $deal->travel_until?->toDateString(),
            'timezone' => 'Africa/Lagos',
        ];
    }
}

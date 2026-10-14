<?php

namespace App\Travel\Pricing;

use App\Models\TravelOffer;

final class FlightPricePresenter
{
    public function __construct(private readonly ExchangeRateService $rates, private readonly FlightPromotionPresenter $promotions) {}

    public function present(TravelOffer $offer, string $currency): array
    {
        $total = $this->rates->convertMinor($offer->selling_total_minor, $offer->currency, $currency);
        // Every component must use the currency actually returned by conversion.
        $currency = $total['currency'];
        $convert = fn (int $amount): int => $this->rates->convertMinor($amount, $offer->currency, $currency)['amount_minor'];
        $markdown = $convert((int) $offer->markdown_minor);
        $promotion = $convert((int) $offer->deal_discount_minor);

        return [
            'price' => [
                'currency' => $currency,
                'total_minor' => $total['amount_minor'],
                'original_total_minor' => $total['amount_minor'] + $markdown + $promotion,
                'discount_minor' => $markdown + $promotion,
                'promotion_discount_minor' => $promotion,
                'markdown_minor' => $markdown,
                'markup_minor' => $convert((int) $offer->markup_minor),
                'base_minor' => $convert((int) data_get($offer->fare_summary, 'base_minor', 0)),
                'taxes_minor' => $convert((int) data_get($offer->fare_summary, 'taxes_minor', 0)),
            ],
            'promotion' => $this->promotions->present($offer->flight_deal_id, $promotion),
        ];
    }
}

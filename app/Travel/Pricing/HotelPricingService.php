<?php

namespace App\Travel\Pricing;

use App\Models\PricingSetting;

final class HotelPricingService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
        private readonly PercentagePriceAdjustment $adjustments,
    ) {}

    /** @param array<string, mixed> $offer */
    public function price(array $offer, string $displayCurrency, ?int $nights = null): array
    {
        $setting = PricingSetting::query()->where('product_type', 'hotel')->first();
        $total = (int) $offer['total_minor'];
        $adjustment = $this->adjustments->calculate($total, $setting);
        $markup = $adjustment['markup_minor'];
        $selling = $this->exchangeRates->convertMinor($adjustment['total_minor'], $offer['currency'], $displayCurrency);
        $nightly = $nights !== null
            ? (int) round($selling['amount_minor'] / max(1, $nights))
            : $this->exchangeRates->convertMinor($this->adjustments->calculate((int) $offer['nightly_minor'], $setting)['total_minor'], $offer['currency'], $displayCurrency)['amount_minor'];

        return [
            'provider_total_minor' => $total,
            'markup_minor' => $markup,
            'markdown_minor' => $adjustment['markdown_minor'],
            'adjustment_type' => $adjustment['type'],
            'adjustment_percent' => $adjustment['percent'],
            'selling_total_minor' => $adjustment['total_minor'],
            'display_currency' => $selling['currency'],
            'display_total_minor' => $selling['amount_minor'],
            'display_nightly_minor' => $nightly,
            'display_markup_minor' => $this->exchangeRates->convertMinor($markup, $offer['currency'], $displayCurrency)['amount_minor'],
            'display_markdown_minor' => $this->exchangeRates->convertMinor($adjustment['markdown_minor'], $offer['currency'], $displayCurrency)['amount_minor'],
            'exchange_rate' => $selling['rate'],
        ];
    }
}

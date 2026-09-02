<?php

namespace App\Travel\Pricing;

use App\Models\PricingSetting;

final class HotelPricingService
{
    public function __construct(private readonly ExchangeRateService $exchangeRates) {}

    /** @param array<string, mixed> $offer */
    public function price(array $offer, string $displayCurrency): array
    {
        $setting = PricingSetting::query()->where('product_type', 'hotel')->where('enabled', true)->first();
        $value = (float) ($setting?->markup_value ?? 0);
        $total = (int) $offer['total_minor'];

        if ($value > 0 && $setting?->markup_type === 'fixed') {
            $converted = $this->exchangeRates->convertMinor((int) round($value * 100), $setting->currency, $offer['currency']);
            $markup = $converted['currency'] === $offer['currency'] ? $converted['amount_minor'] : 0;
        } elseif ($value > 0) {
            $markup = (int) ceil($total * $value / 100);
        } else {
            $markup = 0;
        }

        $selling = $this->exchangeRates->convertMinor($total + $markup, $offer['currency'], $displayCurrency);
        $nightly = $this->exchangeRates->convertMinor((int) $offer['nightly_minor'], $offer['currency'], $displayCurrency);

        return [
            'provider_total_minor' => $total,
            'markup_minor' => $markup,
            'selling_total_minor' => $total + $markup,
            'display_currency' => $selling['currency'],
            'display_total_minor' => $selling['amount_minor'],
            'display_nightly_minor' => $nightly['amount_minor'],
            'display_markup_minor' => $this->exchangeRates->convertMinor($markup, $offer['currency'], $displayCurrency)['amount_minor'],
            'exchange_rate' => $selling['rate'],
        ];
    }
}

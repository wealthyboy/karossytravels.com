<?php

namespace App\Travel\Pricing;

use App\Models\PricingSetting;
use App\Travel\Data\FlightOffer;

final class OfferPricingService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
        private readonly FlightDealMatcher $deals,
        private readonly PercentagePriceAdjustment $adjustments,
    ) {}

    /** @return array{provider_total_minor:int,markup_minor:int,selling_total_minor:int,display_currency:string,display_base_minor:int,display_taxes_minor:int,display_markup_minor:int,display_total_minor:int,exchange_rate:?float} */
    public function price(FlightOffer $offer, string $channel, string $displayCurrency, string $productType = 'airline', ?string $departureDate = null, ?string $origin = null, ?string $destination = null): array
    {
        $setting = PricingSetting::query()->where('product_type', $productType)->first();
        $adjustment = $this->adjustments->calculate($offer->totalMinor, $setting);
        $markup = $adjustment['markup_minor'];
        $grossSellingMinor = $adjustment['total_minor'];
        $deal = $productType === 'airline'
            ? $this->deals->discount($offer->validatingAirline, $offer->segments, $grossSellingMinor, $offer->currency, $departureDate, $origin, $destination)
            : ['deal_id' => null, 'discount_minor' => 0];
        $sellingMinor = max(0, $grossSellingMinor - $deal['discount_minor']);
        $base = $this->exchangeRates->convertMinor($offer->baseMinor, $offer->currency, $displayCurrency);
        $taxes = $this->exchangeRates->convertMinor($offer->taxesMinor, $offer->currency, $displayCurrency);
        $displayMarkup = $this->exchangeRates->convertMinor($markup, $offer->currency, $displayCurrency);
        $displayMarkdown = $this->exchangeRates->convertMinor($adjustment['markdown_minor'], $offer->currency, $displayCurrency);
        $displayDiscount = $this->exchangeRates->convertMinor($adjustment['markdown_minor'] + $deal['discount_minor'], $offer->currency, $displayCurrency);
        $selling = $this->exchangeRates->convertMinor($sellingMinor, $offer->currency, $displayCurrency);

        return [
            'provider_total_minor' => $offer->totalMinor,
            'markup_minor' => $markup,
            'markdown_minor' => $adjustment['markdown_minor'],
            'adjustment_type' => $adjustment['type'],
            'adjustment_percent' => $adjustment['percent'],
            'selling_total_minor' => $sellingMinor,
            'deal_id' => $deal['deal_id'],
            'deal_discount_minor' => $deal['discount_minor'],
            'display_currency' => $selling['currency'],
            'display_base_minor' => $base['amount_minor'],
            'display_taxes_minor' => $taxes['amount_minor'],
            'display_markup_minor' => $displayMarkup['amount_minor'],
            'display_markdown_minor' => $displayMarkdown['amount_minor'],
            'display_discount_minor' => $displayDiscount['amount_minor'],
            'display_total_minor' => $selling['amount_minor'],
            'exchange_rate' => $selling['rate'],
        ];
    }
}

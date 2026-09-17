<?php

namespace App\Travel\Pricing;

use App\Models\FlightDeal;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Support\Carbon;

final class FlightDealMatcher
{
    public function __construct(private readonly ExchangeRateService $rates) {}

    /** @return array{deal_id:int|null,discount_minor:int} */
    public function discount(string $airlineCode, array $segments, int $priceMinor, string $currency, ?string $departureDate = null, ?string $origin = null, ?string $destination = null): array
    {
        $first = (array) ($segments[0] ?? []);
        $last = (array) ($segments[count($segments) - 1] ?? []);
        $origin ??= (string) data_get($first, 'origin', '');
        $destination ??= (string) data_get($last, 'destination', '');
        $departureDate ??= (string) data_get($first, 'departure_at', '');
        $date = $departureDate !== '' ? Carbon::parse($departureDate)->toDateString() : null;

        if ($date === null || trim($airlineCode) === '') return ['deal_id' => null, 'discount_minor' => 0];

        $deal = FlightDeal::query()->with('airline')
            ->where('active', true)
            ->whereHas('airline', fn ($query) => $query->where('active', true)->where('code', strtoupper(trim($airlineCode))))
            ->whereDate('travel_from', '<=', $date)
            ->whereDate('travel_until', '>=', $date)
            ->where(function ($query) use ($origin, $destination): void {
                $query->whereNull('origin_airport')->orWhere(function ($query) use ($origin, $destination): void {
                    $query->where('origin_airport', strtoupper($origin))->where('destination_airport', strtoupper($destination));
                });
            })
            ->orderByDesc('priority')->orderByDesc('discount_value')->first();

        if (! $deal) return ['deal_id' => null, 'discount_minor' => 0];

        $discount = $deal->discount_type === 'percentage'
            ? (int) round($priceMinor * ((float) $deal->discount_value / 100))
            : $this->fixedDiscount($deal, $currency);

        return ['deal_id' => $deal->id, 'discount_minor' => min(max(0, $discount), $priceMinor)];
    }

    private function fixedDiscount(FlightDeal $deal, string $currency): int
    {
        $discountCurrency = strtoupper((string) $deal->discount_currency);
        if ($discountCurrency === '') return 0;

        return $this->rates->convertMinor((int) round(((float) $deal->discount_value) * 100), $discountCurrency, strtoupper($currency))['amount_minor'];
    }
}

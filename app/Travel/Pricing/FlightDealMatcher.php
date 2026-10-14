<?php

namespace App\Travel\Pricing;

use App\Models\Airline;
use App\Models\FlightDeal;
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
        try {
            $date = $departureDate !== '' ? Carbon::parse($departureDate)->toDateString() : null;
        } catch (\Throwable) {
            return ['deal_id' => null, 'discount_minor' => 0];
        }
        $bookingDate = now('Africa/Lagos')->toDateString();
        $airlineCode = strtoupper(trim($airlineCode));

        if ($date === null || $airlineCode === '' || $priceMinor <= 0) {
            return ['deal_id' => null, 'discount_minor' => 0];
        }

        $knownActiveAirline = Airline::where('code', $airlineCode)->where('active', true)->exists();
        $deals = FlightDeal::query()
            ->where('active', true)
            ->whereDoesntHave('homepageOffers', fn ($query) => $query->where('active', false)->orWhere('promotion_enabled', false))
            ->where(function ($query) use ($airlineCode, $knownActiveAirline): void {
                $query->where('all_airlines', true);
                if ($knownActiveAirline) {
                    $query->orWhereJsonContains('airline_codes', $airlineCode)
                        ->orWhere(function ($query) use ($airlineCode): void {
                            $query->whereNull('airline_codes')->whereHas('airline', fn ($airline) => $airline->where('active', true)->where('code', $airlineCode));
                        });
                }
            })
            ->where(fn ($query) => $query->whereNull('travel_from')->orWhereDate('travel_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('travel_until')->orWhereDate('travel_until', '>=', $date))
            ->where(fn ($query) => $query->whereNull('booking_from')->orWhereDate('booking_from', '<=', $bookingDate))
            ->where(fn ($query) => $query->whereNull('booking_until')->orWhereDate('booking_until', '>=', $bookingDate))
            ->where(function ($query) use ($origin): void {
                $query->whereNull('origin_airport')->orWhere('origin_airport', strtoupper(trim($origin)));
            })
            ->where(fn ($query) => $query->whereNull('destination_airport')->orWhere('destination_airport', strtoupper(trim($destination))))
            ->get();

        // One rule only: highest priority, then greatest actual saving. Never stack.
        $matches = $deals->map(function (FlightDeal $deal) use ($priceMinor, $currency): array {
            $discount = $deal->discount_type === 'percentage'
                ? (int) round($priceMinor * ((float) $deal->discount_value / 100))
                : $this->fixedDiscount($deal, $currency);

            return ['deal_id' => $deal->id, 'discount_minor' => min(max(0, $discount), $priceMinor), 'priority' => $deal->priority];
        })->filter(fn (array $match): bool => $match['discount_minor'] > 0)
            ->sort(fn (array $a, array $b): int => ($b['priority'] <=> $a['priority']) ?: ($b['discount_minor'] <=> $a['discount_minor']) ?: ($a['deal_id'] <=> $b['deal_id']));
        $best = $matches->first();

        return $best ? ['deal_id' => $best['deal_id'], 'discount_minor' => $best['discount_minor']] : ['deal_id' => null, 'discount_minor' => 0];
    }

    private function fixedDiscount(FlightDeal $deal, string $currency): int
    {
        $discountCurrency = strtoupper((string) $deal->discount_currency);
        if ($discountCurrency === '') {
            return 0;
        }

        return $this->rates->convertMinor((int) round(((float) $deal->discount_value) * 100), $discountCurrency, strtoupper($currency))['amount_minor'];
    }
}

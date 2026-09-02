<?php

namespace App\Travel\Pricing;

use App\Models\CurrencySetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ExchangeRateService
{
    public function rate(string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) {
            return 1.0;
        }

        $rates = $this->usdRates();
        $fromRate = $from === 'USD' ? 1.0 : ($rates[$from] ?? null);
        $toRate = $to === 'USD' ? 1.0 : ($rates[$to] ?? null);
        if (! $fromRate || ! $toRate) {
            return null;
        }

        $rate = $toRate / $fromRate;
        $setting = CurrencySetting::query()->where('code', $to)->where('enabled', true)->first();
        $percent = (float) ($setting?->adjustment_percent ?? 0);
        if ($setting?->adjustment_type !== 'none') {
            $adjustment = $setting?->adjustment_mode === 'fixed' ? $percent : $rate * ($percent / 100);
            if ($setting?->adjustment_type === 'markup') {
                $rate += $adjustment;
            }
            if ($setting?->adjustment_type === 'markdown') {
                $rate = max(0, $rate - $adjustment);
            }
        }

        return $rate;
    }

    /** @return array{amount_minor:int,currency:string,rate:?float} */
    public function convertMinor(int $amountMinor, string $from, string $to): array
    {
        $rate = $this->rate($from, $to);
        if ($rate === null) {
            return ['amount_minor' => $amountMinor, 'currency' => strtoupper($from), 'rate' => null];
        }

        return ['amount_minor' => (int) round($amountMinor * $rate), 'currency' => strtoupper($to), 'rate' => $rate];
    }

    /** @return array<string, float> */
    public function liveUsdRates(): array
    {
        return ['USD' => 1.0, ...$this->fetchLiveUsdRates()];
    }

    /** @return array<string, float> */
    private function usdRates(): array
    {
        $settings = CurrencySetting::query()->where('enabled', true)->get()->keyBy('code');
        $manual = $settings->filter(fn (CurrencySetting $setting) => $setting->manual_rate !== null)
            ->map(fn (CurrencySetting $setting) => (float) $setting->manual_rate)->all();

        $fallback = array_map('floatval', (array) config('travel.currency.fallback_usd_rates', []));

        return ['USD' => 1.0, ...$fallback, ...$this->fetchLiveUsdRates(), ...$manual];
    }

    /** @return array<string, float> */
    private function fetchLiveUsdRates(): array
    {
        $live = Cache::remember('travel:exchange-rates:USD', now()->addHours((int) config('travel.currency.cache_hours', 6)), function (): array {
            try {
                $response = Http::acceptJson()->timeout((int) config('travel.currency.timeout', 5))
                    ->get((string) config('travel.currency.rates_url'));

                return $response->successful() ? (array) $response->json('rates', []) : [];
            } catch (Throwable) {
                return [];
            }
        });

        return array_map('floatval', $live);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCurrencySettingRequest;
use App\Models\CurrencySetting;
use App\Support\AuditLogger;
use App\Support\CurrencyMetadata;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

final class CurrencySettingController extends Controller
{
    public function edit(ExchangeRateService $rates): View
    {
        $settings = CurrencySetting::query()->get()->keyBy('code');
        $liveRates = collect($rates->liveUsdRates())->sortKeys()->map(function (float $liveRate, string $code) use ($settings, $rates): array {
            return ['code' => $code, ...CurrencyMetadata::for($code), 'live_rate' => $liveRate, 'effective_rate' => $rates->rate('USD', $code), 'setting' => $settings->get($code)];
        });

        return view('admin.settings.currency', compact('liveRates'));
    }

    public function update(UpdateCurrencySettingRequest $request, AuditLogger $audit): RedirectResponse
    {
        foreach ($request->validated('currencies') as $code => $values) {
            $currency = ctype_digit((string) $code)
                ? CurrencySetting::query()->findOrFail((int) $code)
                : CurrencySetting::query()->firstOrNew(['code' => strtoupper($code)]);
            $before = $currency->toArray();
            $currency->name ??= strtoupper($code);
            $currency->symbol ??= strtoupper($code);
            $values['enabled'] = (bool) ($values['enabled'] ?? false);
            $values['manual_rate'] = filled($values['manual_rate'] ?? null) ? $values['manual_rate'] : null;
            $values['adjustment_percent'] = $values['adjustment_type'] === 'none' ? null : ($values['adjustment_percent'] ?? 0);
            $currency->fill($values)->save();
            $audit->record('currency.updated', $currency->code.' exchange settings updated.', $currency, $before, $currency->fresh()->toArray());
        }
        Cache::forget('travel:exchange-rates:USD');

        return back()->with('success', 'Currency settings updated successfully.');
    }

    public function refresh(ExchangeRateService $rates, AuditLogger $audit): RedirectResponse
    {
        Cache::forget('travel:exchange-rates:USD');
        $freshRates = $rates->liveUsdRates();

        $audit->record(
            'currency.rates_refreshed',
            'Achu Internal Exchange Rate cache was manually refreshed.',
            null,
            null,
            ['rate_count' => count($freshRates)],
        );

        if (count($freshRates) <= 1) {
            return back()->with('error', 'Fresh market rates could not be retrieved. Existing currency settings remain unchanged.');
        }

        return back()->with('success', count($freshRates).' market rates were refreshed successfully.');
    }
}

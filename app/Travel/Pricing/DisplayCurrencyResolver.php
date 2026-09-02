<?php

namespace App\Travel\Pricing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class DisplayCurrencyResolver
{
    public function resolve(Request $request): string
    {
        $supported = array_map('strtoupper', config('travel.currency.supported', ['NGN', 'USD']));
        $selected = $request->hasSession() ? strtoupper((string) $request->session()->get('display_currency', '')) : '';
        if (in_array($selected, $supported, true)) {
            return $selected;
        }

        $accountCurrency = strtoupper((string) $request->user()?->currency_code);
        if (in_array($accountCurrency, $supported, true)) {
            return $accountCurrency;
        }

        $country = $this->countryCode($request);

        return $country === 'NG' ? 'NGN' : 'USD';
    }

    private function countryCode(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'X-Country-Code', 'X-AppEngine-Country'] as $header) {
            if ($request->header($header)) {
                return strtoupper((string) $request->header($header));
            }
        }
        if ($request->ip() === '127.0.0.1' || $request->ip() === '::1') {
            return config('travel.currency.local_country', 'NG');
        }
        if (! config('travel.currency.geo_lookup_enabled')) {
            return null;
        }

        return Cache::remember('travel:country:'.sha1((string) $request->ip()), now()->addDay(), function () use ($request): ?string {
            try {
                $url = str_replace('{ip}', (string) $request->ip(), (string) config('travel.currency.geo_url'));
                $response = Http::acceptJson()->timeout(3)->get($url);

                return $response->successful() ? strtoupper((string) $response->json('country_code')) : null;
            } catch (Throwable) {
                return null;
            }
        });
    }
}

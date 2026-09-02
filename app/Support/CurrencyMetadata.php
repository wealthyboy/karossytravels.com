<?php

namespace App\Support;

final class CurrencyMetadata
{
    /** @var array<string, string> */
    private const SYMBOLS = [
        'AED' => 'AED ',
        'CAD' => 'CA$',
        'EUR' => '€',
        'GBP' => '£',
        'NGN' => '₦',
        'USD' => '$',
        'ZAR' => 'R',
    ];

    /** @var array<string, array{country:string, flag:string}> */
    private const CURRENCIES = [
        'AED' => ['country' => 'United Arab Emirates', 'flag' => '🇦🇪'],
        'AUD' => ['country' => 'Australia', 'flag' => '🇦🇺'],
        'BRL' => ['country' => 'Brazil', 'flag' => '🇧🇷'],
        'CAD' => ['country' => 'Canada', 'flag' => '🇨🇦'],
        'CHF' => ['country' => 'Switzerland', 'flag' => '🇨🇭'],
        'CNY' => ['country' => 'China', 'flag' => '🇨🇳'],
        'CZK' => ['country' => 'Czech Republic', 'flag' => '🇨🇿'],
        'DKK' => ['country' => 'Denmark', 'flag' => '🇩🇰'],
        'EGP' => ['country' => 'Egypt', 'flag' => '🇪🇬'],
        'EUR' => ['country' => 'European Union', 'flag' => '🇪🇺'],
        'GBP' => ['country' => 'United Kingdom', 'flag' => '🇬🇧'],
        'GHS' => ['country' => 'Ghana', 'flag' => '🇬🇭'],
        'HKD' => ['country' => 'Hong Kong', 'flag' => '🇭🇰'],
        'HUF' => ['country' => 'Hungary', 'flag' => '🇭🇺'],
        'IDR' => ['country' => 'Indonesia', 'flag' => '🇮🇩'],
        'ILS' => ['country' => 'Israel', 'flag' => '🇮🇱'],
        'INR' => ['country' => 'India', 'flag' => '🇮🇳'],
        'JPY' => ['country' => 'Japan', 'flag' => '🇯🇵'],
        'KES' => ['country' => 'Kenya', 'flag' => '🇰🇪'],
        'KRW' => ['country' => 'South Korea', 'flag' => '🇰🇷'],
        'MAD' => ['country' => 'Morocco', 'flag' => '🇲🇦'],
        'MXN' => ['country' => 'Mexico', 'flag' => '🇲🇽'],
        'MYR' => ['country' => 'Malaysia', 'flag' => '🇲🇾'],
        'NGN' => ['country' => 'Nigeria', 'flag' => '🇳🇬'],
        'NOK' => ['country' => 'Norway', 'flag' => '🇳🇴'],
        'NZD' => ['country' => 'New Zealand', 'flag' => '🇳🇿'],
        'PHP' => ['country' => 'Philippines', 'flag' => '🇵🇭'],
        'PLN' => ['country' => 'Poland', 'flag' => '🇵🇱'],
        'QAR' => ['country' => 'Qatar', 'flag' => '🇶🇦'],
        'RUB' => ['country' => 'Russia', 'flag' => '🇷🇺'],
        'RWF' => ['country' => 'Rwanda', 'flag' => '🇷🇼'],
        'SAR' => ['country' => 'Saudi Arabia', 'flag' => '🇸🇦'],
        'SEK' => ['country' => 'Sweden', 'flag' => '🇸🇪'],
        'SGD' => ['country' => 'Singapore', 'flag' => '🇸🇬'],
        'THB' => ['country' => 'Thailand', 'flag' => '🇹🇭'],
        'TRY' => ['country' => 'Türkiye', 'flag' => '🇹🇷'],
        'TZS' => ['country' => 'Tanzania', 'flag' => '🇹🇿'],
        'UGX' => ['country' => 'Uganda', 'flag' => '🇺🇬'],
        'USD' => ['country' => 'United States', 'flag' => '🇺🇸'],
        'XAF' => ['country' => 'Central Africa', 'flag' => '🌍'],
        'XOF' => ['country' => 'West Africa', 'flag' => '🌍'],
        'ZAR' => ['country' => 'South Africa', 'flag' => '🇿🇦'],
        'ZMW' => ['country' => 'Zambia', 'flag' => '🇿🇲'],
    ];

    /** @return array{country:string, flag:string} */
    public static function for(string $code): array
    {
        return self::CURRENCIES[strtoupper($code)] ?? ['country' => 'International', 'flag' => '🌐'];
    }

    public static function symbol(string $code): string
    {
        $code = strtoupper($code);

        return self::SYMBOLS[$code] ?? $code.' ';
    }

    public static function format(int $minor, string $code, int $decimals = 2): string
    {
        return self::symbol($code).number_format($minor / 100, $decimals);
    }
}

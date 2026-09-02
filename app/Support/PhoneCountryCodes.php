<?php

namespace App\Support;

final class PhoneCountryCodes
{
    /** @return array<int, array{country:string, flag:string, dial:string}> */
    public static function options(): array
    {
        return [
            ['country' => 'Nigeria', 'flag' => '🇳🇬', 'dial' => '+234'],
            ['country' => 'Ghana', 'flag' => '🇬🇭', 'dial' => '+233'],
            ['country' => 'United Kingdom', 'flag' => '🇬🇧', 'dial' => '+44'],
            ['country' => 'United States', 'flag' => '🇺🇸', 'dial' => '+1'],
            ['country' => 'Canada', 'flag' => '🇨🇦', 'dial' => '+1'],
            ['country' => 'South Africa', 'flag' => '🇿🇦', 'dial' => '+27'],
            ['country' => 'Kenya', 'flag' => '🇰🇪', 'dial' => '+254'],
            ['country' => 'Ethiopia', 'flag' => '🇪🇹', 'dial' => '+251'],
            ['country' => 'Rwanda', 'flag' => '🇷🇼', 'dial' => '+250'],
            ['country' => 'United Arab Emirates', 'flag' => '🇦🇪', 'dial' => '+971'],
            ['country' => 'Saudi Arabia', 'flag' => '🇸🇦', 'dial' => '+966'],
            ['country' => 'Qatar', 'flag' => '🇶🇦', 'dial' => '+974'],
            ['country' => 'France', 'flag' => '🇫🇷', 'dial' => '+33'],
            ['country' => 'Germany', 'flag' => '🇩🇪', 'dial' => '+49'],
            ['country' => 'Italy', 'flag' => '🇮🇹', 'dial' => '+39'],
            ['country' => 'Spain', 'flag' => '🇪🇸', 'dial' => '+34'],
            ['country' => 'Netherlands', 'flag' => '🇳🇱', 'dial' => '+31'],
            ['country' => 'India', 'flag' => '🇮🇳', 'dial' => '+91'],
            ['country' => 'China', 'flag' => '🇨🇳', 'dial' => '+86'],
            ['country' => 'Australia', 'flag' => '🇦🇺', 'dial' => '+61'],
        ];
    }

    public static function normalize(?string $dialCode, ?string $number): string
    {
        $number = trim((string) $number);
        if (str_starts_with($number, '+')) {
            return '+'.preg_replace('/\D+/', '', substr($number, 1));
        }

        $dial = '+'.preg_replace('/\D+/', '', (string) $dialCode);
        $local = preg_replace('/\D+/', '', $number);

        return $dial.ltrim($local, '0');
    }
}

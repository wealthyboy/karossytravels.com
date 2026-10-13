<?php

namespace App\Support;

final class CountryOptions
{
    /**
     * Two-letter country codes and English names, alphabetically ordered.
     * The bundled catalogue comes from i18n-iso-countries 7.14.0.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        static $countries;

        return $countries ??= json_decode(
            file_get_contents(resource_path('data/countries.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}

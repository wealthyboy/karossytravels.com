<?php

namespace App\Travel;

use App\Models\TravelOffer;

final class FlightPassportRequirements
{
    /** @var array<string, string>|null */
    private static ?array $airportCountries = null;

    public function requiredFor(TravelOffer $offer): bool
    {
        $segments = $offer->itinerary;
        if (! is_array($segments) || $segments === []) {
            return true;
        }

        $country = null;
        foreach ($segments as $segment) {
            // A technical stop without its own airport endpoints cannot be
            // safely classified as domestic from the available itinerary.
            if (! is_array($segment) || (int) ($segment['stops'] ?? 0) > 0) {
                return true;
            }
            // Check every connection, not just the search's first/last airport.
            foreach (['origin', 'destination'] as $endpoint) {
                $code = strtoupper(trim((string) ($segment[$endpoint] ?? '')));
                $endpointCountry = $this->airportCountries()[$code] ?? null;
                if ($endpointCountry === null || ($country !== null && $endpointCountry !== $country)) {
                    return true;
                }
                $country = $endpointCountry;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    private function airportCountries(): array
    {
        if (self::$airportCountries === null) {
            // Country/IATA pairs taken from the mobile app's existing airport
            // catalog. Unknown airports retain the international requirement.
            $groups = json_decode(file_get_contents(resource_path('data/airport-countries.json')), true, flags: JSON_THROW_ON_ERROR);
            self::$airportCountries = [];
            foreach ($groups as $country => $codes) {
                foreach (explode(' ', $codes) as $code) {
                    self::$airportCountries[$code] = $country;
                }
            }
        }

        return self::$airportCountries;
    }
}

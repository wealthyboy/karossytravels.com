<?php

namespace App\Travel\Data;

use InvalidArgumentException;

final readonly class FlightOffer
{
    /**
     * @param  array<int, array<string, mixed>>  $segments
     */
    public function __construct(
        public string $providerReference,
        public string $validatingAirline,
        public array $segments,
        public string $currency,
        public int $baseMinor,
        public int $taxesMinor,
        public int $totalMinor,
        public bool $refundable,
    ) {
        if ($providerReference === '' || $segments === [] || $totalMinor < 0) {
            throw new InvalidArgumentException('The travel system returned an invalid flight offer.');
        }
    }

    /** @param array<string, mixed> $offer */
    public static function fromProvider(array $offer): self
    {
        $price = $offer['price'] ?? [];

        return new self(
            providerReference: (string) ($offer['provider_reference'] ?? $offer['id'] ?? ''),
            validatingAirline: (string) ($offer['validating_airline'] ?? ''),
            segments: (array) ($offer['segments'] ?? []),
            currency: (string) ($price['currency'] ?? ''),
            baseMinor: (int) ($price['base_minor'] ?? 0),
            taxesMinor: (int) ($price['taxes_minor'] ?? 0),
            totalMinor: (int) ($price['total_minor'] ?? 0),
            refundable: (bool) ($offer['refundable'] ?? false),
        );
    }
}

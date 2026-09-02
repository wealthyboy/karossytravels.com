<?php

namespace App\Support;

use App\Enums\TravelService;

final class ServiceCatalog
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return [
            $this->item(TravelService::Flights, 'Flights', 'Search and book domestic and international flights', true, 'travel_api'),
            $this->item(TravelService::Hotels, 'Hotels', 'Find accommodation around the world', false, 'travel_api'),
            $this->item(TravelService::Holidays, 'Holidays', 'Curated holiday packages and experiences', false, 'karossy'),
            $this->item(TravelService::Charter, 'Charter', 'Private and group charter requests', false, 'karossy'),
            $this->item(TravelService::Pilgrimage, 'Pilgrimage', 'Faith-based travel packages and support', false, 'karossy'),
            $this->item(TravelService::Visas, 'Visas', 'Visa assistance and application tracking', false, 'karossy'),
            $this->item(TravelService::Cars, 'Cars', 'Airport transfers and car rental', false, 'travel_api'),
        ];
    }

    /** @return array<string, mixed> */
    private function item(
        TravelService $service,
        string $name,
        string $description,
        bool $enabled,
        string $source,
    ): array {
        return [
            'key' => $service->value,
            'name' => $name,
            'description' => $description,
            'enabled' => $enabled,
            'source' => $source,
        ];
    }
}

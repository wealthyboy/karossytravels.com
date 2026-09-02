<?php

namespace App\Travel\Providers;

use App\Travel\Contracts\HotelProvider;
use App\Travel\TravelApi\TravelApiClient;
use App\Travel\TravelApi\TravelApiHotelAvailMapper;
use App\Travel\TravelApi\TravelApiHotelSearchRequestBuilder;

final class TravelApiHotelProvider implements HotelProvider
{
    public function __construct(
        private readonly TravelApiClient $client,
        private readonly TravelApiHotelSearchRequestBuilder $builder,
        private readonly TravelApiHotelAvailMapper $mapper,
    ) {}

    public function name(): string { return 'travel_api'; }

    public function search(array $criteria): array
    {
        return $this->mapper->map($this->client->post(
            (string) config('services.travel.travel_api.hotel_avail_path'),
            $this->builder->build($criteria),
        ));
    }
}

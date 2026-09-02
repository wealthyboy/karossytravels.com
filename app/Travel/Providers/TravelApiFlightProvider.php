<?php

namespace App\Travel\Providers;

use App\Travel\Contracts\FlightProvider;
use App\Travel\TravelApi\TravelApiClient;
use App\Travel\TravelApi\TravelApiFlightSearchRequestBuilder;
use App\Travel\TravelApi\TravelApiGroupedItineraryMapper;

final class TravelApiFlightProvider implements FlightProvider
{
    public function __construct(
        private readonly TravelApiClient $client,
        private readonly TravelApiFlightSearchRequestBuilder $requestBuilder,
        private readonly TravelApiGroupedItineraryMapper $responseMapper,
    ) {}

    public function name(): string
    {
        return 'travel_api';
    }

    public function search(array $criteria): array
    {
        $response = $this->client->shopFlights($this->requestBuilder->build($criteria));

        return $this->responseMapper->map($response);
    }

    /** @return array<string, mixed> */
    public function connectionStatus(): array
    {
        return $this->client->status();
    }
}

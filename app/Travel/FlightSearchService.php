<?php

namespace App\Travel;

use App\Models\AnalyticsEvent;
use App\Models\FlightSearch;
use App\Travel\Contracts\FlightProvider;
use App\Travel\Data\FlightOffer;
use App\Travel\Pricing\OfferPricingService;
use App\Support\TravelLogger;
use Illuminate\Support\Facades\DB;
use Throwable;

final class FlightSearchService
{
    public function __construct(
        private readonly FlightProvider $provider,
        private readonly OfferPricingService $pricing,
        private readonly TravelLogger $travelLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    public function search(array $criteria): array
    {
        $startedAt = hrtime(true);
        try {
            $providerOffers = $this->provider->search($criteria);
        } catch (Throwable $exception) {
            $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
            $this->travelLogger->record('flight', 'search', $this->provider->name(), $criteria, [], [
                'status' => 'failed', 'session_id' => $criteria['session_id'] ?? null,
                'duration_ms' => $durationMs, 'error_message' => $exception->getMessage(),
            ]);
            throw $exception;
        }
        $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
        $channel = auth()->user()?->isB2b() ? 'b2b' : (auth()->user()?->isAdmin() ? 'admin' : 'consumer');

        [$search, $offers] = DB::transaction(function () use ($criteria, $providerOffers, $durationMs, $channel): array {
            $search = FlightSearch::create([
                ...$criteria,
                'user_id' => auth()->id(),
                'channel' => $channel,
                'provider' => $this->provider->name(),
                'result_count' => count($providerOffers),
                'duration_ms' => $durationMs,
            ]);

            $offers = collect($providerOffers)->map(function (array $rawOffer) use ($search, $channel, $criteria): array {
                $offer = FlightOffer::fromProvider($rawOffer);
                $price = $this->pricing->price($offer, $channel, $criteria['currency']);
                $expiresAt = now()->addMinutes((int) config('travel.offers.ttl_minutes', 15));

                $stored = $search->offers()->create([
                    'provider' => $this->provider->name(),
                    'provider_reference' => $offer->providerReference,
                    'channel' => $channel,
                    'currency' => $offer->currency,
                    'provider_total_minor' => $price['provider_total_minor'],
                    'markup_minor' => $price['markup_minor'],
                    'selling_total_minor' => $price['selling_total_minor'],
                    'itinerary' => $offer->segments,
                    'fare_summary' => [
                        'base_minor' => $offer->baseMinor,
                        'taxes_minor' => $offer->taxesMinor,
                        'validating_airline' => $offer->validatingAirline,
                        'refundable' => $offer->refundable,
                    ],
                    'expires_at' => $expiresAt,
                ]);

                return [
                    'id' => $stored->id,
                    'validating_airline' => $offer->validatingAirline,
                    'segments' => $offer->segments,
                    'price' => [
                        'currency' => $price['display_currency'],
                        'base_minor' => $price['display_base_minor'],
                        'taxes_minor' => $price['display_taxes_minor'],
                        'markup_minor' => $price['display_markup_minor'],
                        'total_minor' => $price['display_total_minor'],
                        'provider_currency' => $offer->currency,
                        'exchange_rate' => $price['exchange_rate'],
                    ],
                    'refundable' => $offer->refundable,
                    'expires_at' => $expiresAt->toIso8601String(),
                ];
            })->all();

            return [$search, $offers];
        });

        AnalyticsEvent::create([
            'event' => 'flight_search_completed',
            'service' => 'flights',
            'funnel_step' => 'search_results',
            'session_id' => $criteria['session_id'],
            'source' => $this->provider->name(),
            'properties' => [
                'origin' => $criteria['origin'],
                'destination' => $criteria['destination'],
                'trip_type' => $criteria['trip_type'],
                'cabin' => $criteria['cabin'],
                'search_id' => $search->id,
                'result_count' => count($offers),
                'duration_ms' => $durationMs,
            ],
            'occurred_at' => now(),
        ]);

        $this->travelLogger->record('flight', 'search', $this->provider->name(), $criteria, [
            'search_id' => $search->id,
            'offer_count' => count($offers),
            'currency' => data_get($offers, '0.price.currency', $criteria['currency']),
        ], ['session_id' => $criteria['session_id'], 'duration_ms' => $durationMs]);

        return [
            'provider' => $this->provider->name(),
            'search_id' => $search->id,
            'currency' => data_get($offers, '0.price.currency', $criteria['currency']),
            'offers' => $offers,
        ];
    }
}

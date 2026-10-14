<?php

namespace App\Travel;

use App\Models\AnalyticsEvent;
use App\Models\HotelSearch;
use App\Travel\Contracts\HotelProvider;
use App\Travel\Pricing\HotelPricingService;
use App\Support\TravelLogger;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HotelSearchService
{
    public function __construct(
        private readonly HotelProvider $provider,
        private readonly HotelPricingService $pricing,
        private readonly TravelLogger $travelLogger,
    ) {}

    /** @param array<string, mixed> $criteria */
    public function search(array $criteria): array
    {
        $criteria['children_ages'] = array_map('intval', $criteria['children_ages'] ?? []);
        $startedAt = hrtime(true);
        try {
            $providerOffers = $this->provider->search($criteria);
        } catch (Throwable $exception) {
            $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
            $this->travelLogger->record('hotel', 'search', $this->provider->name(), $criteria, [], [
                'status' => 'failed', 'session_id' => $criteria['session_id'] ?? null,
                'duration_ms' => $durationMs, 'error_message' => $exception->getMessage(),
            ]);
            throw $exception;
        }
        $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
        $channel = auth()->user()?->isB2b() ? 'b2b' : (auth()->user()?->isAdmin() ? 'admin' : 'consumer');

        [$search, $offers] = DB::transaction(function () use ($criteria, $providerOffers, $durationMs, $channel): array {
            $search = HotelSearch::create([
                ...$criteria, 'user_id' => auth()->id(), 'channel' => $channel,
                'provider' => $this->provider->name(), 'result_count' => count($providerOffers),
                'duration_ms' => $durationMs,
            ]);

            $offers = collect($providerOffers)->map(function (array $offer) use ($search, $criteria): array {
                $nights = max(1, (int) \Illuminate\Support\Carbon::parse($criteria['check_in'])->diffInDays(\Illuminate\Support\Carbon::parse($criteria['check_out'])));
                $price = $this->pricing->price($offer, $criteria['currency'], $nights);
                $expiresAt = now()->addMinutes((int) config('travel.offers.ttl_minutes', 15));
                $stored = $search->offers()->create([
                    ...collect($offer)->except(['total_minor'])->all(),
                    'provider' => $this->provider->name(),
                    'provider_total_minor' => $price['provider_total_minor'],
                    'markup_minor' => $price['markup_minor'],
                    'markdown_minor' => $price['markdown_minor'],
                    'pricing' => [...($offer['pricing'] ?? []), 'adjustment_type' => $price['adjustment_type'], 'adjustment_percent' => $price['adjustment_percent']],
                    'selling_total_minor' => $price['selling_total_minor'],
                    'expires_at' => $expiresAt,
                ]);

                return [
                    ...$offer, 'id' => $stored->id,
                    'price' => [
                        'currency' => $price['display_currency'],
                        'total_minor' => $price['display_total_minor'],
                        'nightly_minor' => $price['display_nightly_minor'],
                        'markup_minor' => $price['display_markup_minor'],
                        'markdown_minor' => $price['display_markdown_minor'],
                        'discount_minor' => $price['display_markdown_minor'],
                    ],
                    'expires_at' => $expiresAt->toIso8601String(),
                ];
            })->all();

            return [$search, $offers];
        });

        AnalyticsEvent::create([
            'event' => 'hotel_search_completed', 'service' => 'hotels', 'funnel_step' => 'search_results',
            'session_id' => $criteria['session_id'], 'source' => $this->provider->name(),
            'properties' => [
                'destination_code' => $criteria['destination_code'], 'search_id' => $search->id,
                'result_count' => count($offers), 'duration_ms' => $durationMs,
            ], 'occurred_at' => now(),
        ]);

        $this->travelLogger->record('hotel', 'search', $this->provider->name(), $criteria, [
            'search_id' => $search->id,
            'offer_count' => count($offers),
            'currency' => data_get($offers, '0.price.currency', $criteria['currency']),
        ], ['session_id' => $criteria['session_id'], 'duration_ms' => $durationMs]);

        return ['provider' => $this->provider->name(), 'search_id' => $search->id, 'currency' => data_get($offers, '0.price.currency', $criteria['currency']), 'offers' => $offers];
    }
}

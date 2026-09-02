<?php

namespace App\Travel;

use App\Models\AnalyticsEvent;
use App\Models\TravelOffer;
use App\Travel\Data\FlightOffer;
use App\Travel\Pricing\OfferPricingService;
use App\Travel\TravelApi\TravelApiClient;
use App\Travel\TravelApi\TravelApiFlightRevalidationRequestBuilder;
use App\Travel\TravelApi\TravelApiGroupedItineraryMapper;
use App\Support\TravelLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class FlightRevalidationService
{
    public function __construct(
        private readonly TravelApiClient $client,
        private readonly TravelApiFlightRevalidationRequestBuilder $requestBuilder,
        private readonly TravelApiGroupedItineraryMapper $mapper,
        private readonly OfferPricingService $pricing,
        private readonly TravelLogger $travelLogger,
    ) {}

    /** @return array<string, mixed> */
    public function revalidate(TravelOffer $offer): array
    {
        $offer->loadMissing('flightSearch');
        abort_if($offer->expires_at->isPast(), 410, 'This fare has expired. Please search again.');
        $before = $offer->selling_total_minor;

        if ($offer->provider === 'fake') {
            $matched = [
                'provider_reference' => $offer->provider_reference,
                'segments' => $offer->itinerary,
                'price' => [
                    'currency' => $offer->currency,
                    'base_minor' => (int) data_get($offer->fare_summary, 'base_minor', $offer->provider_total_minor),
                    'taxes_minor' => (int) data_get($offer->fare_summary, 'taxes_minor', 0),
                    'total_minor' => $offer->provider_total_minor,
                ],
                'validating_airline' => data_get($offer->fare_summary, 'validating_airline'),
                'refundable' => (bool) data_get($offer->fare_summary, 'refundable', false),
            ];
        } else {
            $requestPayload = $this->requestBuilder->build($offer);
            try {
                $response = $this->client->revalidateFlightOffer($requestPayload);
            } catch (\Throwable $exception) {
                $this->travelLogger->record('flight', 'revalidation', $offer->provider, ['offer_id' => $offer->id], [], [
                    'status' => 'failed', 'session_id' => $offer->flightSearch->session_id,
                    'offer_id' => $offer->id, 'error_message' => $exception->getMessage(),
                ]);
                throw $exception;
            }
            $candidates = $this->mapper->map($response);
            $matched = collect($candidates)->first(
                fn (array $candidate): bool => $this->sameFlights($offer->itinerary, $candidate['segments'] ?? [])
            );
            if (! is_array($matched)) {
                Log::warning('Travel API revalidation returned no matching itinerary.', [
                    'offer_id' => $offer->id,
                    'candidate_count' => count($candidates),
                    'itinerary_count' => data_get($response, 'groupedItineraryResponse.statistics.itineraryCount'),
                    'messages' => data_get($response, 'groupedItineraryResponse.messages', []),
                    'stored_flights' => collect($offer->itinerary)->map(fn (array $segment): string => $this->flightSignature($segment))->all(),
                    'candidate_flights' => collect($candidates)->take(3)->map(fn (array $candidate): array => collect($candidate['segments'] ?? [])->map(fn (array $segment): string => $this->flightSignature($segment))->all())->all(),
                ]);
                throw new RuntimeException('The live travel system could not confirm this itinerary. Please choose another fare.');
            }
        }

        $normalized = FlightOffer::fromProvider($matched);
        $priced = $this->pricing->price($normalized, $offer->channel, $offer->currency);

        DB::transaction(function () use ($offer, $normalized, $priced, $matched): void {
            $offer->update([
                'provider_reference' => $normalized->providerReference,
                'provider_total_minor' => $priced['provider_total_minor'],
                'markup_minor' => $priced['markup_minor'],
                'selling_total_minor' => $priced['selling_total_minor'],
                'itinerary' => $normalized->segments,
                'fare_summary' => [
                    ...($offer->fare_summary ?? []),
                    'base_minor' => $normalized->baseMinor,
                    'taxes_minor' => $normalized->taxesMinor,
                    'validating_airline' => $normalized->validatingAirline,
                    'refundable' => $normalized->refundable,
                    'order_offer_id' => data_get($matched, 'order_offer_id'),
                    'selected_offer_item_ids' => data_get($matched, 'selected_offer_item_ids', []),
                ],
                'last_validated_at' => now(),
                'expires_at' => now()->addMinutes((int) config('travel.offers.ttl_minutes', 15)),
            ]);
        });

        AnalyticsEvent::create([
            'event' => 'flight_offer_revalidated',
            'service' => 'flights',
            'funnel_step' => 'revalidated',
            'session_id' => $offer->flightSearch->session_id,
            'source' => $offer->provider,
            'properties' => ['offer_id' => $offer->id, 'price_changed' => $before !== $offer->fresh()->selling_total_minor],
            'occurred_at' => now(),
        ]);

        $offer->refresh();

        $this->travelLogger->record('flight', 'revalidation', $offer->provider, [
            'offer_id' => $offer->id,
            'stored_total_minor' => $before,
        ], [
            'available' => true,
            'price_changed' => $before !== $offer->selling_total_minor,
            'total_minor' => $offer->selling_total_minor,
            'currency' => $offer->currency,
        ], ['session_id' => $offer->flightSearch->session_id, 'offer_id' => $offer->id]);

        return [
            'offer_id' => $offer->id,
            'available' => true,
            'price_changed' => $before !== $offer->selling_total_minor,
            'old_total_minor' => $before,
            'total_minor' => $offer->selling_total_minor,
            'currency' => $offer->currency,
            'last_validated_at' => $offer->last_validated_at?->toIso8601String(),
        ];
    }

    /** @param array<int, array<string, mixed>> $stored @param array<int, array<string, mixed>> $candidate */
    private function sameFlights(array $stored, array $candidate): bool
    {
        // TravelApi can correct an overnight connection date/time during revalidation.
        // The request already pins every segment and booking class, so match the
        // returned itinerary by ordered flight identity and then persist TravelApi's
        // newly validated schedule instead of rejecting it for a time change.
        return collect($stored)->map(fn (array $segment): string => $this->flightIdentity($segment))->values()->all()
            === collect($candidate)->map(fn (array $segment): string => $this->flightIdentity($segment))->values()->all();
    }

    /** @param array<string, mixed> $segment */
    private function flightIdentity(array $segment): string
    {
        return implode('|', [
            strtoupper((string) ($segment['origin'] ?? '')),
            strtoupper((string) ($segment['destination'] ?? '')),
            strtoupper((string) ($segment['flight_number'] ?? '')),
            strtoupper((string) ($segment['marketing_airline'] ?? '')),
        ]);
    }

    /** @param array<string, mixed> $segment */
    private function flightSignature(array $segment): string
    {
        $departure = (string) ($segment['departure_at'] ?? '');
        if ($departure !== '') {
            $departure = CarbonImmutable::parse($departure)->format('Y-m-d\TH:i:s');
        }

        return implode('|', [
            strtoupper((string) ($segment['origin'] ?? '')),
            strtoupper((string) ($segment['destination'] ?? '')),
            strtoupper((string) ($segment['flight_number'] ?? '')),
            $departure,
        ]);
    }
}

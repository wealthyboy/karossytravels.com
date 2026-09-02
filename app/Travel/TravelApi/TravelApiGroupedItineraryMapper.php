<?php

namespace App\Travel\TravelApi;

use Carbon\CarbonImmutable;

final class TravelApiGroupedItineraryMapper
{
    /** @param array<string, mixed> $response
     *  @return array<int, array<string, mixed>>
     */
    public function map(array $response): array
    {
        $grouped = (array) ($response['groupedItineraryResponse'] ?? []);
        $schedules = $this->indexById((array) ($grouped['scheduleDescs'] ?? []));
        $legs = $this->indexById((array) ($grouped['legDescs'] ?? []));
        $allowances = $this->indexById((array) ($grouped['baggageAllowanceDescs'] ?? []));
        $offers = [];

        foreach ((array) ($grouped['itineraryGroups'] ?? []) as $groupIndex => $group) {
            $legDescriptions = (array) data_get($group, 'groupDescription.legDescriptions', []);

            foreach ((array) ($group['itineraries'] ?? []) as $itinerary) {
                foreach ((array) ($itinerary['pricingInformation'] ?? []) as $pricingIndex => $pricing) {
                    $fare = (array) ($pricing['fare'] ?? []);
                    $totalFare = (array) ($fare['totalFare'] ?? []);
                    $currency = (string) ($totalFare['currency'] ?? 'USD');
                    $totalMinor = $this->minor($totalFare['totalPrice'] ?? 0);
                    $taxesMinor = $this->minor($totalFare['totalTaxAmount'] ?? 0);
                    $passengerInfo = (array) data_get($fare, 'passengerInfoList.0.passengerInfo', []);
                    $fareSegments = $this->fareSegments($passengerInfo);
                    $baggageBySegment = $this->baggageBySegment($passengerInfo, $allowances);
                    $segments = [];
                    $segmentIndex = 0;
                    $previousArrival = null;

                    foreach ((array) ($itinerary['legs'] ?? []) as $legIndex => $legReference) {
                        $leg = $legs[(int) ($legReference['ref'] ?? 0)] ?? [];
                        $travelDate = (string) ($legDescriptions[$legIndex]['departureDate'] ?? '');

                        foreach ((array) ($leg['schedules'] ?? []) as $scheduleReference) {
                            $schedule = $schedules[(int) ($scheduleReference['ref'] ?? 0)] ?? [];
                            if ($schedule === [] || $travelDate === '') {
                                continue;
                            }

                            $booking = (array) ($fareSegments[$segmentIndex] ?? []);
                            $departureDate = CarbonImmutable::parse($travelDate)
                                ->addDays((int) data_get($schedule, 'departure.dateAdjustment', 0));
                            $arrivalDate = CarbonImmutable::parse($travelDate)
                                ->addDays((int) data_get($schedule, 'arrival.dateAdjustment', 0));
                            $departureAt = CarbonImmutable::parse($departureDate->toDateString().'T'.data_get($schedule, 'departure.time'));
                            $arrivalAt = CarbonImmutable::parse($arrivalDate->toDateString().'T'.data_get($schedule, 'arrival.time'));

                            // Some TravelApi responses omit dateAdjustment on overnight connections.
                            // Keep each leg chronological using the preceding segment as a fallback.
                            while ($previousArrival instanceof CarbonImmutable && $departureAt->lessThan($previousArrival)) {
                                $departureAt = $departureAt->addDay();
                                $arrivalAt = $arrivalAt->addDay();
                            }
                            while ($arrivalAt->lessThanOrEqualTo($departureAt)) {
                                $arrivalAt = $arrivalAt->addDay();
                            }

                            $segments[] = [
                                'leg_index' => $legIndex,
                                'origin' => (string) data_get($schedule, 'departure.airport'),
                                'destination' => (string) data_get($schedule, 'arrival.airport'),
                                'departure_at' => $departureAt->format('Y-m-d\\TH:i:sP'),
                                'arrival_at' => $arrivalAt->format('Y-m-d\\TH:i:sP'),
                                'flight_number' => (string) data_get($schedule, 'carrier.marketing').data_get($schedule, 'carrier.marketingFlightNumber'),
                                'marketing_airline' => (string) data_get($schedule, 'carrier.marketing'),
                                'operating_airline' => (string) data_get($schedule, 'carrier.operating'),
                                'equipment' => (string) data_get($schedule, 'carrier.equipment.code'),
                                'duration_minutes' => (int) ($schedule['elapsedTime'] ?? 0),
                                'stops' => (int) ($schedule['stopCount'] ?? 0),
                                'booking_code' => $booking['bookingCode'] ?? null,
                                'cabin' => $this->cabinName((string) ($booking['cabinCode'] ?? '')),
                                'seats_available' => $booking['seatsAvailable'] ?? null,
                                'checked_baggage_pieces' => $baggageBySegment[$segmentIndex] ?? null,
                            ];
                            $previousArrival = $arrivalAt;
                            $segmentIndex++;
                        }
                    }

                    if ($segments === [] || $totalMinor <= 0) {
                        continue;
                    }

                    $offers[] = [
                        'id' => (string) ($itinerary['id'] ?? $groupIndex.'-'.$pricingIndex),
                        'source' => 'travel_api',
                        'provider_reference' => json_encode([
                            'group' => $groupIndex,
                            'itinerary_id' => $itinerary['id'] ?? null,
                            'pricing_index' => $pricingIndex,
                            'pricing_source' => $itinerary['pricingSource'] ?? null,
                        ], JSON_THROW_ON_ERROR),
                        'validating_airline' => (string) ($fare['validatingCarrierCode'] ?? data_get($segments, '0.marketing_airline')),
                        'segments' => $segments,
                        'price' => [
                            'currency' => $currency,
                            'base_minor' => max(0, $totalMinor - $taxesMinor),
                            'taxes_minor' => $taxesMinor,
                            'total_minor' => $totalMinor,
                        ],
                        'refundable' => ! (bool) ($passengerInfo['nonRefundable'] ?? false),
                        'order_offer_id' => data_get($pricing, 'offerId')
                            ?? data_get($fare, 'offerId')
                            ?? data_get($itinerary, 'offerId'),
                        'selected_offer_item_ids' => collect(
                            data_get($pricing, 'selectedOfferItems', data_get($fare, 'selectedOfferItems', []))
                        )->map(fn (mixed $item): ?string => is_array($item) ? ($item['id'] ?? null) : (is_string($item) ? $item : null))
                            ->filter()->values()->all(),
                        'last_ticket_date' => $fare['lastTicketDate'] ?? null,
                        'last_ticket_time' => $fare['lastTicketTime'] ?? null,
                    ];
                }
            }
        }

        return $offers;
    }

    /** @param array<int, array<string, mixed>> $items
     *  @return array<int, array<string, mixed>>
     */
    private function indexById(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $indexed[(int) ($item['id'] ?? 0)] = $item;
        }

        return $indexed;
    }

    /** @param array<string, mixed> $passengerInfo
     *  @return array<int, array<string, mixed>>
     */
    private function fareSegments(array $passengerInfo): array
    {
        $segments = [];
        foreach ((array) ($passengerInfo['fareComponents'] ?? []) as $component) {
            foreach ((array) ($component['segments'] ?? []) as $segment) {
                $segments[] = (array) ($segment['segment'] ?? []);
            }
        }

        return $segments;
    }

    /** @param array<string, mixed> $passengerInfo
     *  @param array<int, array<string, mixed>> $allowances
     *  @return array<int, int>
     */
    private function baggageBySegment(array $passengerInfo, array $allowances): array
    {
        $result = [];
        foreach ((array) ($passengerInfo['baggageInformation'] ?? []) as $baggage) {
            $allowance = $allowances[(int) data_get($baggage, 'allowance.ref', 0)] ?? [];
            foreach ((array) ($baggage['segments'] ?? []) as $segment) {
                $result[(int) ($segment['id'] ?? 0)] = (int) ($allowance['pieceCount'] ?? 0);
            }
        }

        return $result;
    }

    private function minor(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function cabinName(string $code): string
    {
        return match ($code) {
            'F' => 'first',
            'C', 'J' => 'business',
            'P', 'S' => 'premium_economy',
            default => 'economy',
        };
    }
}

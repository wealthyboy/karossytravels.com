<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\TravelOffer;
use App\Travel\FlightRevalidationService;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class FlightOfferController extends Controller
{
    public function __invoke(
        Request $request,
        TravelOffer $offer,
        FlightRevalidationService $revalidation,
        ExchangeRateService $rates,
    ): JsonResponse {
        $currency = strtoupper((string) $request->query('currency', $offer->currency));
        if (! in_array($currency, ['NGN', 'USD'], true)) {
            $currency = strtoupper($offer->currency);
        }

        if ($offer->expires_at->isPast()) {
            return response()->json([
                'message' => 'This fare has expired. Please run a new flight search.',
                'meta' => [
                    'api_version' => 'v1',
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 410);
        }

        try {
            $validation = $revalidation->revalidate($offer);
            $offer->refresh();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'We could not confirm this fare right now. Please retry or choose another flight.',
                'meta' => [
                    'api_version' => 'v1',
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 422);
        }

        $converted = $rates->convertMinor($offer->selling_total_minor, $offer->currency, $currency);

        return ApiResponse::success($request, [
            'offer' => [
                'id' => $offer->id,
                'validating_airline' => data_get($offer->fare_summary, 'validating_airline'),
                'segments' => $offer->itinerary,
                'price' => [
                    'currency' => $converted['currency'],
                    'total_minor' => $converted['amount_minor'],
                ],
                'refundable' => (bool) data_get($offer->fare_summary, 'refundable', false),
                'expires_at' => $offer->expires_at->toIso8601String(),
            ],
            'validation' => [
                'available' => (bool) ($validation['available'] ?? true),
                'price_changed' => (bool) ($validation['price_changed'] ?? false),
            ],
        ], ['currency' => $converted['currency']]);
    }
}

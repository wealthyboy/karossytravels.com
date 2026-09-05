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
    public function __invoke(Request $request, TravelOffer $offer, FlightRevalidationService $revalidation, ExchangeRateService $rates): JsonResponse
    {
        $currency = strtoupper((string) $request->query('currency', $offer->currency));
        if (! in_array($currency, ['NGN', 'USD', 'GBP', 'EUR'], true)) {
            $currency = $offer->currency;
        }

        try {
            $validation = $revalidation->revalidate($offer);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $offer->expires_at?->isPast()
                    ? 'This fare has expired. Please run a new search.'
                    : 'This fare could not be confirmed right now. Please choose another fare.',
            ], $offer->expires_at?->isPast() ? 410 : 422);
        }

        $offer->refresh();
        $total = $rates->convertMinor($offer->selling_total_minor, $offer->currency, $currency);

        return ApiResponse::success($request, [
            'offer' => [
                'id' => $offer->id,
                'validating_airline' => data_get($offer->fare_summary, 'validating_airline'),
                'segments' => $offer->itinerary,
                'refundable' => (bool) data_get($offer->fare_summary, 'refundable', false),
                'price' => ['currency' => $total['currency'], 'total_minor' => $total['amount_minor']],
                'expires_at' => $offer->expires_at?->toIso8601String(),
            ],
            'validation' => $validation,
        ]);
    }
}

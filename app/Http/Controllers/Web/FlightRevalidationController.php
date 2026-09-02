<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TravelOffer;
use App\Travel\FlightRevalidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class FlightRevalidationController extends Controller
{
    public function __invoke(Request $request, TravelOffer $offer, FlightRevalidationService $service): JsonResponse
    {
        try {
            $result = $service->revalidate($offer);

            return response()->json([
                'data' => [
                    ...$result,
                    'continue_url' => route('checkout.travellers', $offer),
                ],
                'meta' => ['request_id' => $request->attributes->get('request_id')],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The live fare could not be confirmed. Please retry or choose another fare.',
                'meta' => ['request_id' => $request->attributes->get('request_id')],
            ], 422);
        }
    }
}

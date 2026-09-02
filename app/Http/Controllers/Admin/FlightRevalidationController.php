<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
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

            return ApiResponse::success($request, [
                ...$result,
                'continue_url' => route('admin.flights.orders.create', $offer),
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

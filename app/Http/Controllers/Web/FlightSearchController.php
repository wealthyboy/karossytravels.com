<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlightSearchRequest;
use App\Http\Responses\ApiResponse;
use App\Travel\FlightSearchService;
use App\Travel\Pricing\DisplayCurrencyResolver;
use Illuminate\Http\JsonResponse;
use Throwable;

final class FlightSearchController extends Controller
{
    public function __invoke(
        FlightSearchRequest $request,
        FlightSearchService $service,
        DisplayCurrencyResolver $currencyResolver,
    ): JsonResponse {
        $criteria = $request->validated();
        $criteria['currency'] = $currencyResolver->resolve($request);
        try {
            $result = $service->search($criteria);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The airline service is temporarily unavailable. Please try again shortly.',
                'meta' => [
                    'api_version' => 'v1',
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 503);
        }

        return ApiResponse::success(
            $request,
            ['offers' => $result['offers']],
            [
                'currency' => $result['currency'],
                'search_id' => $result['search_id'],
                'offer_count' => count($result['offers']),
            ],
        );
    }
}

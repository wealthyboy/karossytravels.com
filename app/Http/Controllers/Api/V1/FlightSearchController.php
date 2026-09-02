<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlightSearchRequest;
use App\Http\Responses\ApiResponse;
use App\Travel\FlightSearchService;
use Illuminate\Http\JsonResponse;

final class FlightSearchController extends Controller
{
    public function __invoke(FlightSearchRequest $request, FlightSearchService $service): JsonResponse
    {
        $result = $service->search($request->validated());

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

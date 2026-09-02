<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\HotelSearchRequest;
use App\Http\Responses\ApiResponse;
use App\Travel\HotelSearchService;
use App\Travel\Pricing\DisplayCurrencyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Throwable;

final class HotelResultsController extends Controller
{
    public function __invoke(
        HotelSearchRequest $request,
        DisplayCurrencyResolver $currencyResolver,
    ): View {
        $criteria = $request->validated();
        $criteria['currency'] = $currencyResolver->resolve($request);

        return view('hotels.results', ['criteria' => $criteria]);
    }

    public function search(
        HotelSearchRequest $request,
        HotelSearchService $service,
        DisplayCurrencyResolver $currencyResolver,
    ): JsonResponse {
        $criteria = $request->validated();
        $criteria['currency'] = $currencyResolver->resolve($request);
        try {
            $result = $service->search($criteria);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'We are having trouble connecting to the hotel network. Please check your connection and try again shortly.',
                'meta' => [
                    'api_version' => 'v1',
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 503);
        }

        $properties = collect($result['offers'])->groupBy('hotel_code')->map(function ($rates): array {
            $sorted = $rates->sortBy('price.total_minor')->values();

            return ['offer' => $sorted->first(), 'rates' => $sorted];
        })->values();

        $html = view('hotels._results', [
            'properties' => $properties,
            'currency' => $result['currency'],
        ])->render();

        return ApiResponse::success($request, ['html' => $html], [
            'currency' => $result['currency'],
            'search_id' => $result['search_id'],
            'offer_count' => count($result['offers']),
            'property_count' => $properties->count(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Travel\HomepageFlightOfferCatalog;
use App\Travel\Pricing\DisplayCurrencyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FlightPromotionController extends Controller
{
    public function __invoke(Request $request, HomepageFlightOfferCatalog $offers, DisplayCurrencyResolver $currencies): JsonResponse
    {
        $currency = $currencies->resolve($request);

        return ApiResponse::success($request, ['offers' => $offers->cards($currency)], ['currency' => $currency]);
    }
}

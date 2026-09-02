<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Support\ServiceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppBootstrapController extends Controller
{
    public function __invoke(Request $request, ServiceCatalog $catalog): JsonResponse
    {
        return ApiResponse::success($request, [
            'application' => [
                'name' => config('app.name'),
                'environment' => app()->environment(),
                'default_currency' => config('travel.default_currency'),
            ],
            'services' => $catalog->all(),
            'features' => config('travel.features'),
            'support' => array_filter((array) config('travel.support')),
        ]);
    }
}

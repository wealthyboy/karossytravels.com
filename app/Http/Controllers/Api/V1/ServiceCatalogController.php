<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Support\ServiceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ServiceCatalogController extends Controller
{
    public function __invoke(Request $request, ServiceCatalog $catalog): JsonResponse
    {
        return ApiResponse::success($request, $catalog->all());
    }
}

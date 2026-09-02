<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiResponse
{
    /**
     * @param mixed $data
     * @param array<string, mixed> $meta
     */
    public static function success(Request $request, mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'api_version' => 'v1',
                'request_id' => $request->attributes->get('request_id'),
                ...$meta,
            ],
        ], $status);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnalyticsEventRequest;
use App\Http\Responses\ApiResponse;
use App\Models\AnalyticsEvent;
use Illuminate\Http\JsonResponse;

final class AnalyticsEventController extends Controller
{
    public function __invoke(StoreAnalyticsEventRequest $request): JsonResponse
    {
        $data = $request->validated();

        $event = AnalyticsEvent::create([
            ...$data,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'properties' => $data['properties'] ?? [],
            'ip_hash' => $request->ip()
                ? hash_hmac('sha256', $request->ip(), (string) config('app.key'))
                : null,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        return ApiResponse::success(
            $request,
            ['id' => $event->id, 'accepted' => true],
            status: 202,
        );
    }
}

<?php

namespace App\Support;

use App\Models\TravelLog;
final class TravelLogger
{
    /** @param array<string, mixed> $requestPayload @param array<string, mixed> $responsePayload */
    public function record(
        string $product,
        string $stage,
        ?string $provider,
        array $requestPayload = [],
        array $responsePayload = [],
        array $context = [],
    ): TravelLog {
        $request = request();

        return TravelLog::create([
            'product_type' => $product,
            'stage' => $stage,
            'provider' => $provider,
            'status' => $context['status'] ?? 'success',
            'session_id' => $context['session_id'] ?? null,
            'user_id' => auth()->id(),
            'offer_id' => $context['offer_id'] ?? null,
            'order_id' => $context['order_id'] ?? null,
            'request_id' => $request->attributes->get('request_id'),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'duration_ms' => $context['duration_ms'] ?? null,
            'request_payload' => $this->redact($requestPayload),
            'response_payload' => $this->redact($responsePayload),
            'error_message' => $context['error_message'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function redact(array $payload): array
    {
        $sensitive = ['password', 'token', 'access_token', 'client_secret', 'passport_number', 'card_number', 'cvv', 'authorization'];

        return collect($payload)->mapWithKeys(function (mixed $value, string|int $key) use ($sensitive): array {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                return [$key => '[REDACTED]'];
            }

            if (is_array($value)) {
                return [$key => $this->redact($value)];
            }

            return [$key => $value];
        })->all();
    }
}

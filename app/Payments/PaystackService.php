<?php

namespace App\Payments;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PaystackService
{
    /** @param array<string, mixed> $metadata */
    public function initialize(string $email, int $amountMinor, string $currency, string $reference, array $metadata = [], ?string $callbackUrl = null): array
    {
        $payload = [
            'email' => $email,
            'amount' => (string) $amountMinor,
            'currency' => $currency,
            'reference' => $reference,
            'metadata' => $metadata,
        ];
        if ($callbackUrl) {
            $payload['callback_url'] = $callbackUrl;
        }
        $response = $this->client()->post('/transaction/initialize', $payload);

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException((string) ($response->json('message') ?: 'Payment could not be initialized.'));
        }

        return (array) $response->json('data', []);
    }

    public function verify(string $reference): array
    {
        $response = $this->client()->get('/transaction/verify/'.rawurlencode($reference));
        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException((string) ($response->json('message') ?: 'Payment could not be verified.'));
        }

        return (array) $response->json('data', []);
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        $secret = trim((string) config('services.paystack.secret_key'));
        if ($secret === '') {
            throw new RuntimeException('Online payment is not configured yet. Please contact Karossy support.');
        }

        return Http::baseUrl((string) config('services.paystack.base_url', 'https://api.paystack.co'))
            ->withToken($secret)->acceptJson()->asJson()->timeout(20)->retry(2, 250);
    }
}

<?php

namespace App\Travel\TravelApi;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class TravelApiClient
{
    /** @param array<string, mixed> $configuration */
    public function __construct(private readonly array $configuration) {}

    /** @return array<string, mixed> */
    public function status(): array
    {
        $required = match ($this->configuration['auth_scheme'] ?? 'oauth_client') {
            'oauth_client' => ['client_id', 'client_secret'],
            'bearer_token' => ['access_token'],
            'epr_v2' => ['user_id', 'password', 'pcc', 'domain'],
            'password_grant' => ['client_id', 'client_secret', 'user_id', 'password', 'pcc'],
            default => ['confirmed_auth_scheme'],
        };
        $missing = array_values(array_filter(
            $required,
            fn (string $key): bool => blank($this->configuration[$key] ?? null),
        ));

        return [
            'configured' => $missing === [],
            'environment' => $this->configuration['environment'],
            'auth_scheme' => $this->configuration['auth_scheme'] ?? 'oauth_client',
            'missing' => $missing,
            'format' => 'REST/JSON',
            'base_url' => $this->baseUrl(),
            'token_cached' => Cache::has($this->tokenCacheKey()),
            'token_expires_at' => Cache::get($this->tokenMetadataCacheKey())['expires_at'] ?? null,
        ];
    }

    /** @return array{ready:bool,expires_at:?string} */
    public function authenticate(bool $force = false): array
    {
        $previousToken = null;
        $previousMetadata = null;

        if ($force && ($this->configuration['auth_scheme'] ?? null) !== 'bearer_token') {
            // Keep a still-valid token available if the diagnostic refresh itself
            // cannot reach the supplier. A connection test must not take the
            // live application offline by destroying a usable cached token.
            $previousToken = Cache::get($this->tokenCacheKey());
            $previousMetadata = Cache::get($this->tokenMetadataCacheKey());

            Cache::forget($this->tokenCacheKey());
            Cache::forget($this->tokenMetadataCacheKey());
        }

        try {
            $this->accessToken();
        } catch (\Throwable $exception) {
            if (is_string($previousToken) && $previousToken !== '') {
                $expiresAt = is_array($previousMetadata) ? ($previousMetadata['expires_at'] ?? null) : null;
                $restoreUntil = $expiresAt ? Carbon::parse($expiresAt) : now()->addMinutes(5);

                if ($restoreUntil->isFuture()) {
                    Cache::put($this->tokenCacheKey(), $previousToken, $restoreUntil);
                    if (is_array($previousMetadata)) {
                        Cache::put($this->tokenMetadataCacheKey(), $previousMetadata, $restoreUntil);
                    }
                }
            }

            throw $exception;
        }

        return [
            'ready' => true,
            'expires_at' => Cache::get($this->tokenMetadataCacheKey())['expires_at'] ?? null,
        ];
    }

    /**
     * Return the token already available to Karossy without making a network
     * request. This is intentionally used only by the protected admin supplier
     * diagnostics page.
     */
    public function currentAccessToken(): ?string
    {
        if (($this->configuration['auth_scheme'] ?? null) === 'bearer_token') {
            $configured = (string) ($this->configuration['access_token'] ?? '');

            return $configured !== '' ? $configured : null;
        }

        $cached = Cache::get($this->tokenCacheKey());

        return is_string($cached) && $cached !== '' ? $cached : null;
    }

    /** @param array<string, mixed> $payload */
    public function post(string $path, array $payload): array
    {
        // Defensive: remove any empty agencyCustomerNumber fields to avoid TravelApi validation
        array_walk_recursive($payload, function (&$v, $k) use (&$payload) {
            // noop: we use a second pass below to remove keys, array_walk_recursive cannot unset parent keys
        });

        // Remove top-level or nested empty agencyCustomerNumber keys
        $payload = $this->removeEmptyAgencyNumber($payload);

        // Log the exact JSON payload after cleanup for debugging
        try {
            Log::info('Travel API outbound payload (post-cleanup).', [
                'path' => $path,
                'json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'request_id' => request()->attributes->get('request_id'),
            ]);
        } catch (\Throwable $e) {
            // swallow logging errors
        }

        $response = $this->authenticatedRequest()->post($path, $payload);
        if ($response->failed()) {
            $providerError = $this->providerErrorFrom($response->body());

            Log::warning('Travel API rejected an API request.', [
                'path'           => $path,
                'status'         => $response->status(),
                'content_type'   => $response->header('Content-Type'),
                'provider_error' => $providerError,
                'request_body'   => $payload,
                'response_body'  => str($response->body())->limit(4000)->toString(),
                'request_id'     => request()->attributes->get('request_id'),
            ]);

            throw new RuntimeException('The travel system rejected the request'.($providerError ? ': '.$providerError : ". HTTP {$response->status()}."));
        }

        $json = $response->json();

        if (! is_array($json)) {
            $providerError = $this->providerErrorFrom($response->body());

            Log::warning('Travel API returned an empty or non-JSON response.', [
                'path' => $path,
                'status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'response_body' => str($response->body())->limit(1000)->toString(),
                'request_id' => request()->attributes->get('request_id'),
            ]);

            if ($providerError !== null) {
                throw new RuntimeException('The travel system rejected the request: '.$providerError);
            }

            throw new RuntimeException("The travel system returned no usable data (HTTP {$response->status()}). Please retry or choose another fare.");
        }

        return $json;
    }

    /** Remove empty agencyCustomerNumber keys from payload recursively */
    private function removeEmptyAgencyNumber(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($key === 'agencyCustomerNumber' && ($value === '' || $value === null)) {
                unset($data[$key]);
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->removeEmptyAgencyNumber($value);
            }
        }

        return $data;
    }

    private function providerErrorFrom(string $body): ?string
    {
        $json = json_decode($body, true);
        if (is_array($json)) {
            $additionalMessage = collect((array) data_get($json, 'additionalMessages', []))
                ->first(fn (mixed $message): bool => is_array($message) && in_array(($message['errorCode'] ?? null), ['INVALIDREQ', 'ERR'], true));
            $messages = collect([
                is_array($additionalMessage) ? ($additionalMessage['message'] ?? null) : null,
                data_get($json, 'message'),
                data_get($json, 'errorCode'),
                data_get($json, 'errors.0.message'),
                data_get($json, 'errors.0.errorMessage'),
                data_get($json, 'validationErrors.0.message'),
            ])->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
                ->unique()->values();

            if ($messages->isNotEmpty()) {
                return $messages->implode(' — ');
            }
        }

        if (! str_contains($body, '<Error')) {
            return null;
        }

        if (preg_match('/<Error\b[^>]*\bShortText=["\']([^"\']+)["\'][^>]*>/i', $body, $match) === 1) {
            return html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        if (preg_match('/<Error\b[^>]*>(.*?)<\/Error>/is', $body, $match) === 1) {
            $message = trim(strip_tags(html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8')));

            return $message !== '' ? $message : null;
        }

        return null;
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function shopFlights(array $payload): array
    {
        return $this->post((string) $this->configuration['flight_shop_path'], $payload);
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function revalidateFlightOffer(array $payload): array
    {
        return $this->post((string) $this->configuration['flight_revalidate_path'], $payload);
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function createTripOrder(array $payload): array
    {
        return $this->post((string) $this->configuration['order_create_path'], $payload);
    }

    /** ATPCO / traditional GDS booking via Booking Management API createBooking
     *  @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function createAtpcoBooking(array $payload): array
    {
        return $this->post((string) $this->configuration['booking_create_path'], $payload);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function checkHotelPrice(array $payload): array
    {
        return $this->post((string) $this->configuration['hotel_price_check_path'], $payload);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createHotelBooking(array $payload): array
    {
        return $this->post((string) $this->configuration['hotel_booking_path'], $payload);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function cancelTripOrder(array $payload): array
    {
        return $this->post((string) $this->configuration['order_cancel_path'], $payload);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function changeTripOrder(array $payload): array
    {
        return $this->post((string) $this->configuration['order_change_path'], $payload);
    }

    public function accessToken(): string
    {
        $this->ensureConfigured();

        if (($this->configuration['auth_scheme'] ?? null) === 'bearer_token') {
            return (string) $this->configuration['access_token'];
        }

        $cacheKey = $this->tokenCacheKey();
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $lockSeconds = max(30, ($this->tokenTimeout() * $this->tokenAttempts()) + 10);

        try {
            return Cache::lock($cacheKey.'.lock', $lockSeconds)->block(5, function () use ($cacheKey): string {
                $cachedInsideLock = Cache::get($cacheKey);

                if (is_string($cachedInsideLock) && $cachedInsideLock !== '') {
                    return $cachedInsideLock;
                }

                $response = match ($this->configuration['auth_scheme'] ?? 'oauth_client') {
                    'password_grant' => $this->requestPasswordGrantToken(),
                    'epr_v2' => $this->requestEprV2Token(),
                    default => $this->requestClientCredentialsToken(),
                };

                $token = $response['access_token'] ?? null;

                if (! is_string($token) || $token === '') {
                    throw new RuntimeException('The travel system did not return an access token.');
                }

                $expiresIn = max(60, (int) ($response['expires_in'] ?? 3600) - 60);
                Cache::put($cacheKey, $token, now()->addSeconds($expiresIn));
                Cache::put($this->tokenMetadataCacheKey(), [
                    'expires_at' => now()->addSeconds($expiresIn)->toIso8601String(),
                ], now()->addSeconds($expiresIn));

                return $token;
            });
        } catch (LockTimeoutException $exception) {
            // A parallel flight search may already be refreshing the token.
            // Re-check the cache once before surfacing a descriptive error.
            $cachedAfterWait = Cache::get($cacheKey);

            if (is_string($cachedAfterWait) && $cachedAfterWait !== '') {
                return $cachedAfterWait;
            }

            throw new RuntimeException(
                'Travel API authentication is still in progress: another token request held the authentication lock for more than 5 seconds.',
                0,
                $exception,
            );
        }
    }

    /** @return array<string, mixed> */
    private function requestPasswordGrantToken(): array
    {
        $clientId = (string) $this->configuration['client_id'];
        $clientSecret = (string) $this->configuration['client_secret'];
        $credential = base64_encode(base64_encode($clientId).':'.base64_encode($clientSecret));

        return $this->requestToken($credential, [
            'grant_type' => 'password',
            'username' => $this->configuration['user_id'].'-'.$this->configuration['pcc'].'-AA',
            'password' => (string) $this->configuration['password'],
        ]);
    }

    /** @return array<string, mixed> */
    private function requestEprV2Token(): array
    {
        $userId = 'V1:'.$this->configuration['user_id'].':'.$this->configuration['pcc'].':'.$this->configuration['domain'];
        $credential = base64_encode(
            base64_encode($userId).':'.base64_encode((string) $this->configuration['password'])
        );

        return $this->requestToken($credential, [
            'grant_type' => 'client_credentials',
        ]);
    }

    /** @return array<string, mixed> */
    private function requestClientCredentialsToken(): array
    {
        $credential = base64_encode(
            base64_encode((string) $this->configuration['client_id'])
            .':'.base64_encode((string) $this->configuration['client_secret'])
        );

        return $this->requestToken($credential, [
            'grant_type' => 'client_credentials',
        ]);
    }

    /** @param array<string, mixed> $form
     *  @return array<string, mixed>
     */
    private function requestToken(string $credential, array $form): array
    {
        $attempts = $this->tokenAttempts();
        $timeout = $this->tokenTimeout();
        $connectTimeout = min($timeout, max(1, (int) ($this->configuration['token_connect_timeout'] ?? 10)));
        $retryDelayMs = max(0, (int) ($this->configuration['token_retry_delay_ms'] ?? 750));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::baseUrl($this->baseUrl())
                    ->asForm()
                    ->acceptJson()
                    ->connectTimeout($connectTimeout)
                    ->timeout($timeout)
                    ->withHeaders(['Authorization' => 'Basic '.$credential])
                    ->post((string) $this->configuration['token_path'], $form);
            } catch (ConnectionException $exception) {
                if ($attempt >= $attempts) {
                    throw $exception;
                }

                $this->logTokenRetry($attempt, $attempts, null, $exception->getMessage());
                $this->waitBeforeTokenRetry($retryDelayMs);
                continue;
            }

            if ($response->successful()) {
                $json = $response->json();

                if (! is_array($json)) {
                    throw new RuntimeException('The travel system returned an invalid authentication response.');
                }

                return $json;
            }

            // Retry only failures that are likely transient. Authentication and
            // validation failures (4xx other than 429) should surface immediately.
            $retryable = $response->status() === 429 || $response->serverError();

            if (! $retryable || $attempt >= $attempts) {
                $response->throw();
            }

            $this->logTokenRetry($attempt, $attempts, $response->status(), null);
            $this->waitBeforeTokenRetry($retryDelayMs);
        }

        throw new RuntimeException('The travel system authentication request could not be completed.');
    }

    private function tokenTimeout(): int
    {
        return max(5, (int) ($this->configuration['token_timeout'] ?? 30));
    }

    private function tokenAttempts(): int
    {
        return max(1, (int) ($this->configuration['token_attempts'] ?? 3));
    }

    private function waitBeforeTokenRetry(int $retryDelayMs): void
    {
        if ($retryDelayMs > 0) {
            usleep($retryDelayMs * 1000);
        }
    }

    private function logTokenRetry(int $attempt, int $attempts, ?int $status, ?string $error): void
    {
        Log::warning('Travel API authentication request failed; retrying.', [
            'attempt' => $attempt,
            'max_attempts' => $attempts,
            'status' => $status,
            'error' => $error,
            'request_id' => request()->attributes->get('request_id'),
        ]);
    }

    private function authenticatedRequest(): PendingRequest
    {
        // Keep provider failures inside PHP's request budget so controllers can
        // return a controlled JSON response instead of an HTML fatal-error page.
        $timeout = min(15, max(5, (int) $this->configuration['timeout']));

        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(min(5, $timeout))
            ->timeout($timeout)
            ->retry(2, 250, throw: false)
            ->withHeaders(['X-Request-ID' => request()->attributes->get('request_id', (string) str()->uuid())])
            ->withToken($this->accessToken());
    }

    private function baseUrl(): string
    {
        return $this->configuration['environment'] === 'production'
            ? (string) $this->configuration['production_url']
            : (string) $this->configuration['cert_url'];
    }

    private function tokenCacheKey(): string
    {
        return 'travel_api.access_token.'.($this->configuration['environment'] ?? 'cert');
    }

    private function tokenMetadataCacheKey(): string
    {
        return $this->tokenCacheKey().'.metadata';
    }

    private function ensureConfigured(): void
    {
        $status = $this->status();

        if (! $status['configured']) {
            throw new RuntimeException('The travel integration is not configured: '.implode(', ', $status['missing']));
        }
    }
}

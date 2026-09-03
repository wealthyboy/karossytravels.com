<?php

namespace App\Integrations\Google;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GoogleOAuthClient
{
    public function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    /**
     * @return array{verifier: string, challenge: string}
     */
    public function pkcePair(): array
    {
        $verifier = $this->base64Url(random_bytes(64));

        return [
            'verifier' => $verifier,
            'challenge' => $this->base64Url(hash('sha256', $verifier, true)),
        ];
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        $this->assertConfigured();

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'access_type' => 'online',
            'include_granted_scopes' => 'true',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);

        return rtrim((string) config('services.google.authorization_url'), '?').'?' . $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCode(string $code, string $codeVerifier): array
    {
        $this->assertConfigured();

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout($this->timeout())
                ->post((string) config('services.google.token_url'), [
                    'code' => $code,
                    'client_id' => config('services.google.client_id'),
                    'client_secret' => config('services.google.client_secret'),
                    'redirect_uri' => $this->redirectUri(),
                    'grant_type' => 'authorization_code',
                    'code_verifier' => $codeVerifier,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Could not connect to Google authentication.', 0, $exception);
        }

        if ($response->failed()) {
            $message = (string) ($response->json('error_description') ?: $response->json('error') ?: 'Google rejected the authentication request.');
            throw new RuntimeException($message);
        }

        $payload = $response->json();
        $accessToken = is_array($payload) ? ($payload['access_token'] ?? null) : null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google did not return an access token.');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function userProfile(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout($this->timeout())
                ->get((string) config('services.google.userinfo_url'));
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Could not retrieve your Google profile.', 0, $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('Google could not verify the signed-in account.');
        }

        $profile = $response->json();
        if (! is_array($profile)) {
            throw new RuntimeException('Google returned an invalid profile response.');
        }

        $subject = $profile['sub'] ?? null;
        $email = $profile['email'] ?? null;
        $verified = filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOL);

        if (! is_string($subject) || $subject === '' || ! is_string($email) || $email === '' || ! $verified) {
            throw new RuntimeException('Google could not verify the email address for this account.');
        }

        return $profile;
    }

    public function redirectUri(): string
    {
        $configured = trim((string) config('services.google.redirect'));

        return $configured !== '' ? $configured : route('auth.google.callback');
    }

    private function assertConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('Google Sign-In is not configured yet.');
        }
    }

    private function timeout(): int
    {
        return max(5, (int) config('services.google.timeout', 15));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Integrations\Google\GoogleOAuthClient;
use App\Models\Customer;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class GoogleAuthController extends Controller
{
    public function redirect(Request $request, GoogleOAuthClient $google): RedirectResponse
    {
        try {
            if (! $google->configured()) {
                return redirect()->route('login')->withErrors([
                    'google' => 'Google Sign-In is not configured yet.',
                ]);
            }

            $state = Str::random(64);
            $pkce = $google->pkcePair();

            $request->session()->put('google_oauth_state', $state);
            $request->session()->put('google_oauth_code_verifier', $pkce['verifier']);

            return redirect()->away($google->authorizationUrl($state, $pkce['challenge']));
        } catch (Throwable $exception) {
            $this->logFailure($exception, 'redirect');

            return redirect()->route('login')->withErrors([
                'google' => 'Google Sign-In is temporarily unavailable. Please try again.',
            ]);
        }
    }

    public function callback(Request $request, GoogleOAuthClient $google): RedirectResponse
    {
        if ($request->filled('error')) {
            $request->session()->forget(['google_oauth_state', 'google_oauth_code_verifier']);

            return redirect()->route('login')->withErrors([
                'google' => $request->string('error')->toString() === 'access_denied'
                    ? 'Google Sign-In was cancelled.'
                    : 'Google Sign-In could not be completed. Please try again.',
            ]);
        }

        $expectedState = (string) $request->session()->pull('google_oauth_state', '');
        $codeVerifier = (string) $request->session()->pull('google_oauth_code_verifier', '');
        $receivedState = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if ($expectedState === '' || $receivedState === '' || ! hash_equals($expectedState, $receivedState)) {
            return redirect()->route('login')->withErrors([
                'google' => 'Google Sign-In expired or could not be verified. Please try again.',
            ]);
        }

        if ($code === '' || $codeVerifier === '') {
            return redirect()->route('login')->withErrors([
                'google' => 'Google Sign-In could not be completed. Please try again.',
            ]);
        }

        try {
            $tokens = $google->exchangeCode($code, $codeVerifier);
            $profile = $google->userProfile((string) $tokens['access_token']);
            [$user, $created] = $this->resolveUser($request, $profile);

            if ($user->status !== 'active') {
                return redirect()->route('login')->withErrors([
                    'google' => 'This account is not active. Please contact Karossy support.',
                ]);
            }

            if ($created) {
                event(new Registered($user));
            }

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended(route('home'))->with(
                'success',
                $created ? 'Welcome to Karossy. Your account is ready.' : 'Welcome back, '.$user->name.'.'
            );
        } catch (Throwable $exception) {
            $this->logFailure($exception, 'callback');

            return redirect()->route('login')->withErrors([
                'google' => 'Google Sign-In could not be completed. Please try again.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array{0: User, 1: bool}
     */
    private function resolveUser(Request $request, array $profile): array
    {
        $subject = (string) $profile['sub'];
        $email = strtolower(trim((string) $profile['email']));
        $providerName = trim((string) ($profile['name'] ?? ''));
        $avatar = filled($profile['picture'] ?? null) ? (string) $profile['picture'] : null;

        $linked = SocialAccount::query()
            ->with('user')
            ->where('provider', 'google')
            ->where('provider_user_id', $subject)
            ->first();

        if ($linked?->user) {
            $linked->forceFill([
                'provider_email' => $email,
                'avatar_url' => $avatar,
            ])->save();

            return [$linked->user, false];
        }

        return DB::transaction(function () use ($request, $profile, $subject, $email, $providerName, $avatar): array {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            $created = false;

            if ($user) {
                $otherGoogle = SocialAccount::query()
                    ->where('provider', 'google')
                    ->where('user_id', $user->id)
                    ->where('provider_user_id', '!=', $subject)
                    ->exists();

                if ($otherGoogle) {
                    throw new RuntimeException('This Karossy account is already linked to another Google account.');
                }

                if ($user->email_verified_at === null) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }
            } else {
                [$firstName, $lastName] = $this->profileNames($profile, $providerName, $email);
                $currency = strtoupper((string) $request->session()->get('display_currency', 'NGN'));
                $supported = array_map('strtoupper', (array) config('travel.currency.supported', ['NGN', 'USD']));

                if (! in_array($currency, $supported, true)) {
                    $currency = 'NGN';
                }

                $user = User::create([
                    'name' => trim($firstName.' '.$lastName),
                    'email' => $email,
                    'account_type' => 'b2c',
                    'currency_code' => $currency,
                    'status' => 'active',
                    'password' => Str::random(64),
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();

                $existingCustomer = Customer::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();
                if ($existingCustomer) {
                    if ($existingCustomer->trashed()) {
                        $existingCustomer->restore();
                    }
                    $existingCustomer->forceFill([
                        'user_id' => $user->id,
                        'first_name' => $existingCustomer->first_name ?: $firstName,
                        'last_name' => $existingCustomer->last_name ?: $lastName,
                        'status' => 'active',
                    ])->save();
                } else {
                    Customer::create([
                        'user_id' => $user->id,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'status' => 'active',
                    ]);
                }

                $created = true;
            }

            SocialAccount::updateOrCreate(
                ['provider' => 'google', 'provider_user_id' => $subject],
                [
                    'user_id' => $user->id,
                    'provider_email' => $email,
                    'avatar_url' => $avatar,
                ]
            );

            return [$user, $created];
        });
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array{0: string, 1: string}
     */
    private function profileNames(array $profile, string $providerName, string $email): array
    {
        $firstName = trim((string) ($profile['given_name'] ?? ''));
        $lastName = trim((string) ($profile['family_name'] ?? ''));

        if ($firstName === '' && $providerName !== '') {
            $parts = preg_split('/\s+/', $providerName, 2) ?: [];
            $firstName = trim((string) ($parts[0] ?? ''));
            $lastName = $lastName !== '' ? $lastName : trim((string) ($parts[1] ?? ''));
        }

        if ($firstName === '') {
            $firstName = Str::headline(Str::before($email, '@')) ?: 'Karossy';
        }

        if ($lastName === '') {
            $lastName = 'Traveller';
        }

        return [Str::limit($firstName, 80, ''), Str::limit($lastName, 80, '')];
    }

    private function logFailure(Throwable $exception, string $stage): void
    {
        Log::warning('Google Sign-In failed.', [
            'stage' => $stage,
            'exception' => $exception::class,
            'message' => Str::limit($exception->getMessage(), 300),
        ]);
    }
}

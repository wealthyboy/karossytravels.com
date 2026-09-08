<?php

namespace App\Http\Middleware;

use App\Models\MobileAuthToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();
        $token = $plainToken ? MobileAuthToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first() : null;

        if (! $token || ! $token->user || $token->user->status !== 'active' || ($token->expires_at && $token->expires_at->isPast())) {
            return response()->json(['message' => 'Please sign in to continue.'], 401);
        }

        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('mobile_auth_token', $token);
        $token->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}

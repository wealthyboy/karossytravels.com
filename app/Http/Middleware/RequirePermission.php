<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (app()->isLocal() && ! $request->user()) {
            return $next($request);
        }

        $user = $request->user();
        abort_unless($user instanceof User && $user->hasPermission($permission), 403);

        return $next($request);
    }
}

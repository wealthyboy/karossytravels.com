<?php

namespace App\Providers;

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class GoogleAuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'guest', 'throttle:10,1'])->group(function (): void {
            Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])
                ->name('auth.google.redirect');
            Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
                ->name('auth.google.callback');
        });
    }
}

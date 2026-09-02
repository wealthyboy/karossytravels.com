<?php

use App\Http\Controllers\Api\V1\AnalyticsEventController;
use App\Http\Controllers\Api\V1\AppBootstrapController;
use App\Http\Controllers\Api\V1\FlightSearchController;
use App\Http\Controllers\Api\V1\HotelSearchController;
use App\Http\Controllers\Api\V1\ServiceCatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('app/bootstrap', AppBootstrapController::class)->name('app.bootstrap');
    Route::get('services', ServiceCatalogController::class)->name('services.index');
    Route::post('flights/search', FlightSearchController::class)
        ->middleware('throttle:30,1')
        ->name('flights.search');
    Route::post('hotels/search', HotelSearchController::class)
        ->middleware('throttle:30,1')
        ->name('hotels.search');
    Route::post('analytics/events', AnalyticsEventController::class)
        ->middleware('throttle:120,1')
        ->name('analytics.events.store');
});

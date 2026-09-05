<?php

use App\Http\Controllers\Api\V1\AnalyticsEventController;
use App\Http\Controllers\Api\V1\AppBootstrapController;
use App\Http\Controllers\Api\V1\FlightSearchController;
use App\Http\Controllers\Api\V1\FlightOfferController;
use App\Http\Controllers\Api\V1\HotelSearchController;
use App\Http\Controllers\Api\V1\HotelRoomsController;
use App\Http\Controllers\Api\V1\MobileCheckoutController;
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
    Route::get('flights/offers/{offer}', FlightOfferController::class)
        ->middleware('throttle:30,1')
        ->name('flights.offers.show');
    Route::get('hotels/offers/{offer}/rooms', HotelRoomsController::class)
        ->middleware('throttle:30,1')
        ->name('hotels.offers.rooms');
    Route::post('flights/offers/{offer}/checkout', [MobileCheckoutController::class, 'flight'])->middleware('throttle:10,1')->name('flights.checkout');
    Route::post('hotels/offers/{offer}/checkout', [MobileCheckoutController::class, 'hotel'])->middleware('throttle:10,1')->name('hotels.checkout');
    Route::get('payments/{attempt}/status', [MobileCheckoutController::class, 'status'])->middleware('throttle:60,1')->name('payments.status');
    Route::post('analytics/events', AnalyticsEventController::class)
        ->middleware('throttle:120,1')
        ->name('analytics.events.store');
});

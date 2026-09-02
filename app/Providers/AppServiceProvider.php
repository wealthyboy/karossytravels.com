<?php

namespace App\Providers;

use App\Travel\Contracts\FlightProvider;
use App\Travel\Contracts\HotelProvider;
use App\Travel\Providers\FakeFlightProvider;
use App\Travel\Providers\FakeHotelProvider;
use App\Travel\Providers\TravelApiFlightProvider;
use App\Travel\Providers\TravelApiHotelProvider;
use App\Travel\TravelApi\TravelApiClient;
use App\Travel\TravelApi\TravelApiFlightSearchRequestBuilder;
use App\Travel\TravelApi\TravelApiFlightRevalidationRequestBuilder;
use App\Travel\TravelApi\TravelApiHotelSearchRequestBuilder;
use App\Travel\TravelApi\TravelApiHotelBookingRequestBuilder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TravelApiClient::class, fn (): TravelApiClient => new TravelApiClient(
            (array) config('services.travel.travel_api')
        ));
        $this->app->singleton(TravelApiFlightSearchRequestBuilder::class, fn (): TravelApiFlightSearchRequestBuilder => new TravelApiFlightSearchRequestBuilder(
            (array) config('services.travel.travel_api')
        ));
        $this->app->singleton(TravelApiFlightRevalidationRequestBuilder::class, fn (): TravelApiFlightRevalidationRequestBuilder => new TravelApiFlightRevalidationRequestBuilder(
            (array) config('services.travel.travel_api')
        ));
        $this->app->singleton(TravelApiHotelSearchRequestBuilder::class, fn (): TravelApiHotelSearchRequestBuilder => new TravelApiHotelSearchRequestBuilder(
            (array) config('services.travel.travel_api')
        ));
        $this->app->singleton(TravelApiHotelBookingRequestBuilder::class, fn (): TravelApiHotelBookingRequestBuilder => new TravelApiHotelBookingRequestBuilder(
            (array) config('services.travel.travel_api')
        ));

        $this->app->bind(FlightProvider::class, function (): FlightProvider {
            return match (config('services.travel.provider')) {
                'fake' => new FakeFlightProvider,
                'travel_api' => $this->app->make(TravelApiFlightProvider::class),
                default => throw new \InvalidArgumentException('Unsupported travel integration configured.'),
            };
        });
        $this->app->bind(HotelProvider::class, function (): HotelProvider {
            return match (config('services.travel.provider')) {
                'fake' => new FakeHotelProvider,
                'travel_api' => $this->app->make(TravelApiHotelProvider::class),
                default => throw new \InvalidArgumentException('Unsupported travel integration configured.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));

        // Hotel checkout preflights the live supplier rate before opening Paystack.
        // Keep the production protection conservative, while allowing repeated
        // end-to-end supplier testing in local/testing environments.
        RateLimiter::for('hotel-checkout-payment', function (Request $request): Limit {
            $offer = $request->route('offer');
            $offerId = is_object($offer) && method_exists($offer, 'getKey')
                ? (string) $offer->getKey()
                : (string) $offer;
            $actor = $request->user()?->getAuthIdentifier() ?: 'guest';
            $maxAttempts = app()->environment(['local', 'testing']) ? 30 : 5;

            return Limit::perMinute($maxAttempts)
                ->by($actor.'|'.$request->ip().'|'.$offerId)
                ->response(fn (Request $request, array $headers) => response()->json([
                    'message' => 'Too many checkout attempts were made in a short time. Please wait a moment and try again.',
                ], 429, $headers));
        });

        Paginator::useBootstrapFive();
    }
}

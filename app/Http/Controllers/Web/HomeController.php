<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\HolidayPackage;
use App\Models\FlightOffer;
use App\Travel\Pricing\DisplayCurrencyResolver;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\Request;
use App\Models\Visa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class HomeController extends Controller
{
    public function __invoke(Request $request, DisplayCurrencyResolver $currencyResolver, ExchangeRateService $exchangeRates): View
    {
        $displayCurrency = $currencyResolver->resolve($request);

        [$passportCountries, $visaDestinations] = Schema::hasTable('visas')
            ? [
                Visa::query()->where('active', true)->whereNotNull('passport_country')->distinct()->orderBy('passport_country')->pluck('passport_country'),
                Visa::query()->where('active', true)->distinct()->orderBy('country')->pluck('country'),
            ]
            : [new Collection, new Collection];

        $holidayPackages = Schema::hasTable('holiday_packages')
            ? HolidayPackage::query()->where('active', true)->orderByDesc('featured')->latest()->limit(6)->get()
            : new Collection;

        $flightOffers = Schema::hasTable('flight_offers')
            ? FlightOffer::query()->available()->limit(4)->get()
            : new Collection;

        $holidayPackages->each(function (HolidayPackage $package) use ($displayCurrency, $exchangeRates): void {
            $package->setAttribute('display_price', $exchangeRates->convertMinor(
                $package->price_minor,
                $package->currency,
                $displayCurrency,
            ));
        });

        $flightOffers->each(function (FlightOffer $offer) use ($displayCurrency, $exchangeRates): void {
            $offer->setAttribute('display_price', $exchangeRates->convertMinor(
                $offer->price_minor,
                $offer->currency,
                $displayCurrency,
            ));
        });

        return view('home', [
            'departureDate' => now()->addDays(14)->toDateString(),
            'returnDate' => now()->addDays(21)->toDateString(),
            'passportCountries' => $passportCountries,
            'visaDestinations' => $visaDestinations,
            'holidayPackages' => $holidayPackages,
            'flightOffers' => $flightOffers,
            'displayCurrency' => $displayCurrency,
        ]);
    }
}

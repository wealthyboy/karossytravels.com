<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TravelOffer;
use App\Travel\Pricing\DisplayCurrencyResolver;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class FlightReviewController extends Controller
{
    public function __invoke(Request $request, TravelOffer $offer, DisplayCurrencyResolver $currencyResolver, ExchangeRateService $rates): View|RedirectResponse
    {
        $offer->loadMissing('flightSearch');
        if ($offer->expires_at->isPast()) {
            $search = $offer->flightSearch;

            return redirect()->route('flights.results', [
                'session_id' => (string) Str::uuid(),
                'origin' => $search->origin,
                'destination' => $search->destination,
                'departure_date' => $search->departure_date->toDateString(),
                'return_date' => $search->return_date?->toDateString(),
                'trip_type' => $search->trip_type,
                'cabin' => $search->cabin,
                'adults' => $search->adults,
                'children' => $search->children,
                'infants' => $search->infants,
                'infants_in_seat' => 0,
            ])->with('warning', 'That fare expired, so we refreshed the available flights for you.');
        }
        $currency = $currencyResolver->resolve($request);
        $total = $rates->convertMinor($offer->selling_total_minor, $offer->currency, $currency);
        $provider = $rates->convertMinor($offer->provider_total_minor, $offer->currency, $currency);
        $markup = $rates->convertMinor($offer->markup_minor, $offer->currency, $currency);

        return view('flights.review', compact('offer', 'currency', 'total', 'provider', 'markup'));
    }
}

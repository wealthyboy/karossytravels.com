<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TravelOffer;
use App\Travel\Pricing\DisplayCurrencyResolver;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class FlightReviewController extends Controller
{
    public function __invoke(Request $request, TravelOffer $offer, DisplayCurrencyResolver $currencyResolver, ExchangeRateService $rates): View
    {
        $offer->loadMissing('flightSearch');
        abort_if($offer->expires_at->isPast(), 410, 'This fare has expired. Please search again.');
        $currency = $currencyResolver->resolve($request);
        $total = $rates->convertMinor($offer->selling_total_minor, $offer->currency, $currency);
        $provider = $rates->convertMinor($offer->provider_total_minor, $offer->currency, $currency);
        $markup = $rates->convertMinor($offer->markup_minor, $offer->currency, $currency);

        return view('flights.review', compact('offer', 'currency', 'total', 'provider', 'markup'));
    }
}

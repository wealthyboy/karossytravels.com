<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlightSearchRequest;
use App\Travel\Pricing\DisplayCurrencyResolver;
use Illuminate\View\View;

final class FlightResultsController extends Controller
{
    public function __invoke(
        FlightSearchRequest $request,
        DisplayCurrencyResolver $currencyResolver,
    ): View
    {
        $criteria = $request->validated();
        // Public web searches must use the trusted account/session/location
        // currency. Never trust a hidden form value for customer pricing.
        $criteria['currency'] = $currencyResolver->resolve($request);

        return view('flights.results', ['criteria' => $criteria]);
    }
}

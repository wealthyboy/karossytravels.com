<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\HotelSearchRequest;
use App\Travel\HotelSearchService;
use Illuminate\View\View;

final class HotelResultsController extends Controller
{
    public function __invoke(HotelSearchRequest $request, HotelSearchService $service): View
    {
        $criteria = $request->validated();
        $result = $service->search($criteria);

        return view('hotels.results', compact('criteria') + ['offers' => $result['offers'], 'currency' => $result['currency']]);
    }
}

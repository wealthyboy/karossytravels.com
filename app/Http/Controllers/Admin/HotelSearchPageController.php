<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\HotelSearchRequest;
use App\Travel\HotelSearchService;
use Illuminate\View\View;

final class HotelSearchPageController extends Controller
{
    public function index(): View
    {
        return view('admin.hotels.search', [
            'defaultCheckIn' => now()->addDay()->toDateString(),
            'defaultCheckOut' => now()->addDays(3)->toDateString(),
            'offers' => null,
        ]);
    }

    public function results(HotelSearchRequest $request, HotelSearchService $service): View
    {
        $criteria = $request->validated();
        $result = $service->search($criteria);

        return view('admin.hotels.search', [
            'defaultCheckIn' => $criteria['check_in'], 'defaultCheckOut' => $criteria['check_out'],
            'criteria' => $criteria, 'offers' => $result['offers'], 'currency' => $result['currency'],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class FlightSearchPageController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.flights.search', [
            'defaultDepartureDate' => now()->addDay()->toDateString(),
            'defaultReturnDate' => now()->addDays(8)->toDateString(),
        ]);
    }
}

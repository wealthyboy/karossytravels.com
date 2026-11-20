<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlightSearchRequest;
use App\Http\Requests\HotelSearchRequest;
use App\Http\Responses\ApiResponse;
use App\Travel\FlightSearchService;
use App\Travel\HotelSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Throwable;

final class TravelDeskController extends Controller
{
    public function flights(): View
    {
        return view('crm.travel.flights', [
            'defaultDepartureDate' => now()->addDay()->toDateString(),
            'defaultReturnDate' => now()->addDays(8)->toDateString(),
        ]);
    }

    public function searchFlights(FlightSearchRequest $request, FlightSearchService $service): JsonResponse
    {
        try {
            $result = $service->search($request->validated());

            return ApiResponse::success($request, ['offers' => $result['offers']], [
                'currency' => $result['currency'],
                'search_id' => $result['search_id'],
                'offer_count' => count($result['offers']),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The airline service is temporarily unavailable. Please try again shortly.',
                'meta' => ['request_id' => $request->attributes->get('request_id')],
            ], 503);
        }
    }

    public function hotels(): View
    {
        return view('crm.travel.hotels', [
            'defaultCheckIn' => now()->addDay()->toDateString(),
            'defaultCheckOut' => now()->addDays(3)->toDateString(),
            'offers' => null,
        ]);
    }

    public function searchHotels(HotelSearchRequest $request, HotelSearchService $service): View
    {
        $criteria = $request->validated();

        try {
            $result = $service->search($criteria);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'The hotel service is temporarily unavailable. Please try again shortly.');
        }

        return view('crm.travel.hotels', [
            'defaultCheckIn' => $criteria['check_in'],
            'defaultCheckOut' => $criteria['check_out'],
            'criteria' => $criteria,
            'offers' => $result['offers'],
            'currency' => $result['currency'],
        ]);
    }
}

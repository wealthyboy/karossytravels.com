<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\HotelOffer;
use App\Support\HotelSearchRecovery;
use App\Travel\Pricing\DisplayCurrencyResolver;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class HotelRoomsController extends Controller
{
    public function __invoke(
        Request $request,
        HotelOffer $offer,
        DisplayCurrencyResolver $currencyResolver,
        ExchangeRateService $exchangeRates,
    ): View|RedirectResponse {
        $offer->loadMissing('search');
        if ($offer->expires_at?->isPast()) {
            return redirect()->route('hotels.results', HotelSearchRecovery::parameters($offer->search))
                ->with('warning', 'Those hotel rates expired, so we are refreshing the latest rooms and prices for you.');
        }

        $currency = $currencyResolver->resolve($request);
        $nights = max(1, $offer->search->check_in->diffInDays($offer->search->check_out));
        $rooms = HotelOffer::query()
            ->where('hotel_search_id', $offer->hotel_search_id)
            ->where('hotel_code', $offer->hotel_code)
            ->where('expires_at', '>', now())
            ->orderBy('selling_total_minor')
            ->get()
            ->map(function (HotelOffer $room) use ($currency, $exchangeRates, $nights): array {
                $converted = $exchangeRates->convertMinor($room->selling_total_minor, $room->currency, $currency);

                return [
                    'offer' => $room,
                    'currency' => $converted['currency'],
                    'total_minor' => $converted['amount_minor'],
                    'nightly_minor' => (int) round($converted['amount_minor'] / $nights),
                ];
            });

        if ($rooms->isEmpty()) {
            return redirect()->route('hotels.results', HotelSearchRecovery::parameters($offer->search))
                ->with('warning', 'Those hotel rates expired, so we are refreshing the latest rooms and prices for you.');
        }

        return view('hotels.rooms', compact('offer', 'rooms', 'currency', 'nights'));
    }
}

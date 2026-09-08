<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\HotelOffer;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HotelRoomsController extends Controller
{
    public function __invoke(Request $request, HotelOffer $offer, ExchangeRateService $rates): JsonResponse
    {
        $offer->loadMissing('search');
        if ($offer->expires_at?->isPast()) {
            return response()->json(['message' => 'These hotel rates have expired. Please run a new search.'], 410);
        }

        $currency = strtoupper((string) $request->query('currency', $offer->currency));
        if (! in_array($currency, ['NGN', 'USD', 'GBP', 'EUR'], true)) {
            $currency = $offer->currency;
        }
        $nights = max(1, $offer->search->check_in->diffInDays($offer->search->check_out));
        $rooms = HotelOffer::query()
            ->where('hotel_search_id', $offer->hotel_search_id)
            ->where('hotel_code', $offer->hotel_code)
            ->where('expires_at', '>', now())
            ->orderBy('selling_total_minor')
            ->get()
            ->map(function (HotelOffer $room) use ($currency, $rates, $nights): array {
                $converted = $rates->convertMinor($room->selling_total_minor, $room->currency, $currency);

                return [
                    'id' => $room->id,
                    'name' => $room->room_name ?: $room->rate_name ?: 'Available room',
                    'rate_name' => $room->rate_name,
                    'refundable' => $room->refundable,
                    'breakfast_included' => $room->breakfast_included,
                    'price' => [
                        'currency' => $converted['currency'],
                        'total_minor' => $converted['amount_minor'],
                        'nightly_minor' => (int) round($converted['amount_minor'] / $nights),
                    ],
                    'expires_at' => $room->expires_at?->toIso8601String(),
                ];
            })->values();

        return ApiResponse::success($request, [
            'property' => ['name' => $offer->name, 'rating' => $offer->rating, 'location' => $offer->location],
            'stay' => [
                'check_in' => $offer->search->check_in->toDateString(),
                'check_out' => $offer->search->check_out->toDateString(),
                'nights' => $nights,
                'rooms' => $offer->search->rooms,
                'adults' => $offer->search->adults,
                'children' => $offer->search->children,
            ],
            'rooms' => $rooms,
        ]);
    }
}

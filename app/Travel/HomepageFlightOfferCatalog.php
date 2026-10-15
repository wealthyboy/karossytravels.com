<?php

namespace App\Travel;

use App\Models\FlightOffer;
use App\Travel\Pricing\ExchangeRateService;
use App\Travel\Pricing\FlightDealMatcher;
use App\Travel\Pricing\FlightPromotionPresenter;
use Illuminate\Support\Facades\Schema;

final class HomepageFlightOfferCatalog
{
    public function __construct(private readonly ExchangeRateService $rates, private readonly FlightDealMatcher $deals, private readonly FlightPromotionPresenter $promotions) {}

    public function cards(string $currency): array
    {
        if (! Schema::hasTable('flight_offers')) {
            return [];
        }

        return FlightOffer::available()->limit(4)->get()->map(function (FlightOffer $offer) use ($currency): array {
            $discount = $this->deals->discount((string) $offer->airline_code, [], $offer->price_minor, $offer->currency, $offer->departure_date->toDateString(), $offer->origin_airport, $offer->destination_airport);
            $price = $this->rates->convertMinor(max(0, $offer->price_minor - $discount['discount_minor']), $offer->currency, $currency);
            $saving = $this->rates->convertMinor($discount['discount_minor'], $offer->currency, $price['currency'])['amount_minor'];

            return [
                'id' => $offer->id, 'slug' => $offer->slug,
                'origin_airport' => $offer->origin_airport, 'origin_city' => $offer->origin_city,
                'destination_airport' => $offer->destination_airport, 'destination_city' => $offer->destination_city,
                'airline_name' => $offer->airline_name, 'airline_code' => $offer->airline_code,
                'departure_date' => $offer->departure_date->toDateString(), 'return_date' => $offer->return_date->toDateString(),
                'cabin' => $offer->cabin, 'label' => $offer->label, 'image_url' => $offer->cover_url,
                'price' => ['currency' => $price['currency'], 'total_minor' => $price['amount_minor'], 'original_total_minor' => $price['amount_minor'] + $saving, 'discount_minor' => $saving, 'is_estimate' => true],
                'promotion' => $this->promotions->present($discount['deal_id'], $saving),
                'search_criteria' => [
                    'origin' => $offer->origin_airport, 'destination' => $offer->destination_airport,
                    'departure_date' => $offer->departure_date->toDateString(), 'return_date' => $offer->return_date->toDateString(),
                    'trip_type' => 'round_trip', 'cabin' => $offer->cabin, 'adults' => 1, 'children' => 0, 'children_ages' => [], 'infants' => 0, 'currency' => $currency,
                ],
            ];
        })->all();
    }
}

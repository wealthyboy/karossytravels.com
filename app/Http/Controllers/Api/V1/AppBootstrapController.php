<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Support\ServiceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppBootstrapController extends Controller
{
    public function __invoke(Request $request, ServiceCatalog $catalog): JsonResponse
    {
        return ApiResponse::success($request, [
            'application' => [
                'name' => config('app.name'),
                'environment' => app()->environment(),
                'default_currency' => config('travel.default_currency'),
            ],
            'services' => $catalog->all(),
            'features' => config('travel.features'),
            'support' => array_filter((array) config('travel.support')),
            // Homepage inspiration is owned by the API so mobile clients can be
            // updated without shipping a new application build.
            'homepage' => [
                'destination_moods' => $this->destinationMoods(),
            ],
        ]);
    }

    /** @return array<int, array{key:string,label:string,destinations:array<int, array<string, string>>}> */
    private function destinationMoods(): array
    {
        return [
            ['key' => 'beach', 'label' => 'Beach', 'destinations' => [
                $this->destination('Santorini', 'Greece', 'JTR', 'ROMANTIC GETAWAY', 'https://images.unsplash.com/photo-1570077188670-e3a8d69ac5ff?auto=format&fit=crop&w=1000&q=88'),
                $this->destination('Zanzibar', 'Tanzania', 'ZNZ', 'ISLAND RHYTHM', 'https://images.unsplash.com/photo-1540202404-a2f29016b523?auto=format&fit=crop&w=1000&q=88'),
                $this->destination('Dubai', 'United Arab Emirates', 'DXB', 'CITY AND SUN', 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?auto=format&fit=crop&w=1000&q=88'),
                $this->destination('Cape Town', 'South Africa', 'CPT', 'COAST AND CULTURE', 'https://images.unsplash.com/photo-1580060839134-75a5edca2e99?auto=format&fit=crop&w=1000&q=88'),
            ]],
            ['key' => 'culture', 'label' => 'Culture', 'destinations' => [
                $this->destination('Istanbul', 'Türkiye', 'IST', 'TWO CONTINENTS', 'https://images.unsplash.com/photo-1524231757912-21f4fe3a7200?auto=format&fit=crop&w=1000&q=88'),
                $this->destination('Marrakech', 'Morocco', 'RAK', 'SOUKS AND STORIES', 'https://images.unsplash.com/photo-1597212618440-806262de4f6b?auto=format&fit=crop&w=1000&q=88'),
                $this->destination('Rome', 'Italy', 'ROM', 'HISTORY AT EVERY TURN', 'https://images.unsplash.com/photo-1552832230-c0197dd311b5?auto=format&fit=crop&w=1000&q=88'),
                $this->destination('Kyoto', 'Japan', 'UKY', 'TEMPLES AND TRADITION', 'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?auto=format&fit=crop&w=1000&q=88'),
            ]],
            ['key' => 'family', 'label' => 'Family', 'destinations' => [
                $this->destination('London', 'United Kingdom', 'LON', 'BIG SIGHTS, EASY DAYS', 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?auto=format&fit=crop&w=1000&q=88'),
                $this->destination('Singapore', 'Singapore', 'SIN', 'FAMILY ADVENTURE', 'https://images.unsplash.com/photo-1525625293386-3f8f99389edd?auto=format&fit=crop&w=1000&q=88'),
                $this->destination('Dubai', 'United Arab Emirates', 'DXB', 'FUN FOR EVERY AGE', 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?auto=format&fit=crop&w=1000&q=88'),
                $this->destination('Barcelona', 'Spain', 'BCN', 'CITY, BEACH AND PLAY', 'https://images.unsplash.com/photo-1539037116277-4db20889f2d4?auto=format&fit=crop&w=1000&q=88'),
            ]],
            ['key' => 'wellness', 'label' => 'Wellness', 'destinations' => [
                $this->destination('Bali', 'Indonesia', 'DPS', 'RESET IN PARADISE', 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=1000&q=88'),
                $this->destination('Maldives', 'Maldives', 'MLE', 'BAREFOOT LUXURY', 'https://images.unsplash.com/photo-1514282401047-d79a71a590e8?auto=format&fit=crop&w=1000&q=88'),
                $this->destination('Phuket', 'Thailand', 'HKT', 'SLOW DAYS BY THE SEA', 'https://images.unsplash.com/photo-1589394815804-964ed0be2b86?auto=format&fit=crop&w=1000&q=88'),
                $this->destination('Mauritius', 'Mauritius', 'MRU', 'ISLAND CALM', 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1000&q=88'),
            ]],
        ];
    }

    /** @return array{title:string,subtitle:string,destination_code:string,tag:string,image:string} */
    private function destination(string $title, string $subtitle, string $code, string $tag, string $image): array
    {
        return compact('title', 'subtitle', 'tag', 'image') + ['destination_code' => $code];
    }
}

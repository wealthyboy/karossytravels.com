<?php

namespace Database\Seeders;

use App\Models\HolidayPackage;
use App\Models\FlightOffer;
use App\Models\Visa;
use Illuminate\Database\Seeder;

final class KarossyContentSeeder extends Seeder
{
    public function run(): void
    {
        Visa::query()->updateOrCreate(
            ['slug' => 'austria-tourist-visa-for-nigerian-passports'],
            [
                'name' => 'Austria Tourist Visa',
                'passport_country' => 'Nigeria',
                'passport_country_code' => 'NG',
                'country' => 'Austria',
                'destination_country_code' => 'AT',
                'visa_type' => 'sticker',
                'duration_days' => 28,
                'validity' => 'Schengen C visa: up to 90 days within 180 days',
                'processing_time' => '14–28 business days',
                'fee_cents' => 19425000,
                'currency' => 'NGN',
                'consultation_fee_cents' => 1000000,
                'summary' => 'Guided document preparation and application support for Nigerian passport holders travelling to Austria.',
                'requirements' => "Valid Nigerian passport\nCompleted visa application form\nPassport-sized photographs\nProof of accommodation and travel itinerary\nFinancial statements showing sufficient funds\nTravel health insurance covering at least EUR30,000",
                'requirements_list' => [
                    'Valid Nigerian passport',
                    'Completed visa application form',
                    'Passport-sized photographs',
                    'Proof of accommodation and travel itinerary',
                    'Financial statements showing sufficient funds',
                    'Travel health insurance covering at least EUR30,000',
                ],
                'important_information' => [
                    'Schengen Visa (C Visa) is for stays up to 90 days; National Visa (D Visa) applies to longer stays.',
                    'Applicants must demonstrate strong ties to their country of residence.',
                    'Visa approval remains at the discretion of the issuing authority.',
                ],
                'active' => true,
                'featured' => true,
            ]
        );

        foreach ($this->holidayPackages() as $package) {
            HolidayPackage::query()->updateOrCreate(['slug' => $package['slug']], $package);
        }

        foreach ($this->flightOffers() as $offer) {
            FlightOffer::query()->updateOrCreate(['slug' => $offer['slug']], $offer);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function holidayPackages(): array
    {
        return [
            $this->package('explore-zanzibar', 'Explore Zanzibar', 'Zanzibar', 'Tanzania', 'Tropical island escape', 5, 6, 270000000, ['Return flights', 'Luxury resort stay', 'Guided island tours', 'Breakfast', 'Airport transfers']),
            $this->package('summer-in-egypt', 'Summer in Egypt', 'Cairo & Luxor', 'Egypt', 'Ancient wonders and coastal luxury', 6, 7, 300000000, ['Return flights', 'Premium hotel stay', 'Pyramids of Giza tour', 'Nile experience', 'Airport transfers']),
            $this->package('magical-morocco', 'Magical Morocco', 'Marrakech', 'Morocco', 'Culture, colour and desert adventure', 5, 6, 190000000, ['Return flights', 'Boutique riad stay', 'Marrakech city tour', 'Desert excursion', 'Breakfast']),
            $this->package('couples-escape-to-maldives', 'Couples Escape to Maldives', 'Maldives', 'Maldives', 'Romance in paradise', 5, 6, 500000000, ['Return flights', 'Overwater villa', 'Breakfast and dinner', 'Airport transfer', 'Island experience']),
            $this->package('experience-singapore', 'Experience Singapore', 'Singapore', 'Singapore', 'The ultimate city adventure', 6, 7, 400000000, ['Return flights', 'City hotel stay', 'Gardens by the Bay', 'Universal Studios', 'Airport transfers']),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function flightOffers(): array
    {
        $date = today()->addDays(28);

        return [
            $this->flightOffer('london-dubai', 'LHR', 'London', 'DXB', 'Dubai', 'Emirates', 'EK', $date, 5, 68500, 'USD', 'City escape', 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?auto=format&fit=crop&w=1000&q=82', 10),
            $this->flightOffer('new-york-paris', 'JFK', 'New York', 'CDG', 'Paris', 'Air France', 'AF', $date->copy()->addDays(4), 7, 74200, 'USD', 'Popular', 'https://images.unsplash.com/photo-1502602898657-3e91760cbb34?auto=format&fit=crop&w=1000&q=82', 20),
            $this->flightOffer('singapore-tokyo', 'SIN', 'Singapore', 'HND', 'Tokyo', 'Singapore Airlines', 'SQ', $date->copy()->addDays(8), 6, 76800, 'USD', 'Asia fare', 'https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?auto=format&fit=crop&w=1000&q=82', 30),
            $this->flightOffer('cape-town-doha', 'CPT', 'Cape Town', 'DOH', 'Doha', 'Qatar Airways', 'QR', $date->copy()->addDays(12), 8, 89500, 'USD', 'Fresh fare', 'https://images.unsplash.com/photo-1548013146-72479768bada?auto=format&fit=crop&w=1000&q=82', 40),
            $this->flightOffer('lagos-london', 'LOS', 'Lagos', 'LHR', 'London', 'Virgin Atlantic', 'VS', $date->copy()->addDays(16), 9, 148500, 'USD', 'Direct route', 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?auto=format&fit=crop&w=1000&q=82', 50),
            $this->flightOffer('toronto-lisbon', 'YYZ', 'Toronto', 'LIS', 'Lisbon', 'TAP Air Portugal', 'TP', $date->copy()->addDays(20), 7, 67200, 'USD', 'Europe fare', 'https://images.unsplash.com/photo-1555881400-74d7acaacd8b?auto=format&fit=crop&w=1000&q=82', 60),
        ];
    }

    /** @return array<string, mixed> */
    private function flightOffer(string $slug, string $originAirport, string $originCity, string $destinationAirport, string $destinationCity, string $airlineName, string $airlineCode, $departureDate, int $nights, int $priceMinor, string $currency, string $label, string $imageUrl, int $sortOrder): array
    {
        return [
            'slug' => $slug,
            'origin_airport' => $originAirport,
            'origin_city' => $originCity,
            'destination_airport' => $destinationAirport,
            'destination_city' => $destinationCity,
            'airline_name' => $airlineName,
            'airline_code' => $airlineCode,
            'departure_date' => $departureDate->toDateString(),
            'return_date' => $departureDate->copy()->addDays($nights)->toDateString(),
            'cabin' => 'economy',
            'price_minor' => $priceMinor,
            'currency' => $currency,
            'image_path' => null,
            'image_url' => $imageUrl,
            'label' => $label,
            'sort_order' => $sortOrder,
            'active' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function package(string $slug, string $title, string $destination, string $country, string $tagline, int $nights, int $days, int $priceMinor, array $inclusions): array
    {
        return [
            'slug' => $slug,
            'title' => $title,
            'destination' => $destination,
            'country' => $country,
            'tagline' => $tagline,
            'summary' => 'A carefully coordinated Karossy holiday with flights, accommodation and memorable experiences in one simple package.',
            'nights' => $nights,
            'days' => $days,
            'price_minor' => $priceMinor,
            'currency' => 'NGN',
            'image_path' => null,
            'inclusions' => $inclusions,
            'featured' => true,
            'active' => true,
        ];
    }
}

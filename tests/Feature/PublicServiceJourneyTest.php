<?php

namespace Tests\Feature;

use App\Models\HolidayPackage;
use App\Models\PartnerEnquiry;
use App\Models\Visa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicServiceJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_visa_search_opens_a_backend_managed_result_and_checkout(): void
    {
        $visa = Visa::create([
            'slug' => 'nigeria-to-austria-tourist-visa',
            'name' => 'Tourist Visa',
            'passport_country' => 'Nigeria',
            'passport_country_code' => 'NG',
            'country' => 'Austria',
            'destination_country_code' => 'AT',
            'visa_type' => 'sticker',
            'duration_days' => 90,
            'validity' => 'Up to 90 days within 180 days',
            'processing_time' => '14–28 business days',
            'fee_cents' => 19425000,
            'currency' => 'NGN',
            'consultation_fee_cents' => 1000000,
            'requirements_list' => ['Valid Nigerian passport', 'Proof of accommodation'],
            'active' => true,
        ]);

        $this->get(route('visas.index'))
            ->assertOk()
            ->assertSee('Check visa eligibility');

        $this->get(route('visas.results', [
            'passport_country' => 'Nigeria',
            'destination' => 'Austria',
            'travellers' => 1,
        ]))->assertOk()
            ->assertSee('Tourist Visa')
            ->assertSee('View requirements');

        $this->get(route('visas.show', [$visa, 'travellers' => 1]))
            ->assertOk()
            ->assertSee('Required documents')
            ->assertSee('Valid Nigerian passport');

        $this->get(route('visas.checkout', [$visa, 'travellers' => 1]))
            ->assertOk()
            ->assertSee('Tell us about the traveller')
            ->assertSee('Passport and identity details')
            ->assertSee('Payment is securely processed');
    }

    public function test_driver_can_submit_a_partner_application_without_a_page_reload(): void
    {
        $this->get(route('cars.partners'))
            ->assertOk()
            ->assertSee('Drive with Karossy');

        $this->postJson(route('cars.partners.store'), [
            'name' => 'Ada Driver',
            'email' => 'ada@example.com',
            'phone' => '+2348012345678',
            'city' => 'Lagos',
            'vehicle_type' => 'Executive sedan',
            'vehicle_year' => '2024',
            'message' => 'Available for airport transfers.',
            'terms' => true,
        ])->assertCreated()
            ->assertJsonPath('message', 'Thank you. Our partnership team will contact you shortly.');

        $this->assertDatabaseHas(PartnerEnquiry::class, [
            'email' => 'ada@example.com',
            'type' => 'driver',
            'status' => 'new',
        ]);
    }

    public function test_active_holiday_packages_have_listing_and_detail_pages(): void
    {
        $package = HolidayPackage::create([
            'slug' => 'explore-zanzibar',
            'title' => 'Explore Zanzibar',
            'destination' => 'Zanzibar',
            'country' => 'Tanzania',
            'summary' => 'A curated island escape.',
            'nights' => 5,
            'days' => 6,
            'price_minor' => 270000000,
            'currency' => 'NGN',
            'inclusions' => ['Return flights', 'Guided island tours'],
            'featured' => true,
            'active' => true,
        ]);

        $this->get(route('holidays.index'))
            ->assertOk()
            ->assertSee('Explore Zanzibar');

        $this->get(route('holidays.show', $package))
            ->assertOk()
            ->assertSee('A curated island escape.')
            ->assertSee('Guided island tours');
    }
}

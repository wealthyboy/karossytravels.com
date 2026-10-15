<?php

namespace Tests\Feature\Api;

use App\Models\ServiceEnquiry;
use App\Models\TravelLog;
use App\Support\EnquiryOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MobileServiceEnquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_are_shared_with_the_website_and_include_conditional_visa_choices(): void
    {
        $response = $this->getJson('/api/v1/enquiries/options')->assertOk()->assertJsonPath('meta.api_version', 'v1');
        $this->assertSame(EnquiryOptions::HIRE_TYPES, $response->json('data.car_hire.hire_types'));
        $this->assertSame(EnquiryOptions::RETURN_REQUIRED, $response->json('data.car_hire.return_required'));
        $this->assertSame(EnquiryOptions::visaTypes('Study'), $response->json('data.visa_enquiry.visa_types.Study'));
        $this->assertSame(EnquiryOptions::DOCUMENT_QUESTIONS['Study'], $response->json('data.visa_enquiry.document_questions.Study'));
        $this->assertContains('Nigeria', $response->json('data.visa_enquiry.countries'));
        $this->assertContains('United Kingdom', $response->json('data.visa_enquiry.countries'));
    }

    public function test_mobile_car_hire_saves_to_the_existing_admin_inbox_and_returns_only_a_receipt(): void
    {
        $response = $this->postJson('/api/v1/enquiries/car-hire', $this->car())->assertCreated()->assertJsonStructure(['data' => ['reference', 'message'], 'meta' => ['request_id']]);
        $enquiry = ServiceEnquiry::firstOrFail();
        $this->assertSame($enquiry->reference, $response->json('data.reference'));
        $this->assertStringStartsWith('KCH-', $enquiry->reference);
        $this->assertSame('car_hire', $enquiry->type);
        $this->assertSame('new', $enquiry->status);
        $this->assertSame('SUV', $enquiry->details['vehicle_preference']);
        $this->assertSame(2, $enquiry->details['passengers']);
        $this->assertStringNotContainsString('airport', DB::table('service_enquiries')->value('details'));
        $this->assertSame([], TravelLog::where('stage', 'enquiry')->firstOrFail()->request_payload);
        $response->assertJsonMissingPath('data.details')->assertJsonMissingPath('data.email');
    }

    public function test_return_dates_and_time_order_use_the_same_server_rules_as_the_website(): void
    {
        $this->postJson('/api/v1/enquiries/car-hire', [...$this->car(), 'hire_type' => 'Daily Car Hire'])->assertUnprocessable()->assertJsonValidationErrors(['return_end_date', 'return_end_time']);
        $this->postJson('/api/v1/enquiries/car-hire', [...$this->car(), 'return_end_date' => today()->addWeek()->toDateString(), 'return_end_time' => '08:00'])->assertUnprocessable()->assertJsonValidationErrors('return_end_time');
        $this->assertDatabaseCount('service_enquiries', 0);
        $this->postJson('/api/v1/enquiries/car-hire', [...$this->car(), 'hire_type' => 'Daily Car Hire', 'return_end_date' => today()->addWeeks(2)->toDateString(), 'return_end_time' => '09:00'])->assertCreated();
    }

    public function test_mobile_visa_answers_are_encrypted_and_hidden_answers_are_excluded(): void
    {
        $response = $this->postJson('/api/v1/enquiries/visa', [...$this->visa(), 'refusal_details' => 'Hidden answer', 'countries_previously_visited' => 'Hidden countries'])->assertCreated();
        $enquiry = ServiceEnquiry::firstOrFail();
        $this->assertSame($enquiry->reference, $response->json('data.reference'));
        $this->assertStringStartsWith('KVE-', $enquiry->reference);
        $this->assertSame('Student Visa', $enquiry->details['visa_type']);
        $this->assertArrayNotHasKey('refusal_details', $enquiry->details);
        $this->assertArrayNotHasKey('countries_previously_visited', $enquiry->details);
        $this->assertStringNotContainsString('Student Visa', DB::table('service_enquiries')->value('details'));
        $this->assertSame([], TravelLog::where('stage', 'enquiry')->firstOrFail()->request_payload);
    }

    public function test_invalid_countries_purpose_types_and_missing_answers_never_save(): void
    {
        $data = [...$this->visa(), 'nationality' => 'Unknown', 'visa_type' => 'Tourist Visa', 'duration_number' => 0];
        unset($data['supporting_document']);
        $this->postJson('/api/v1/enquiries/visa', $data)->assertUnprocessable()->assertJsonValidationErrors(['nationality', 'visa_type', 'duration_number', 'supporting_document']);
        $this->assertDatabaseCount('service_enquiries', 0);
    }

    public function test_public_enquiry_submissions_are_rate_limited(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.77']);
        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/v1/enquiries/car-hire', [])->assertUnprocessable();
        }
        $this->postJson('/api/v1/enquiries/car-hire', [])->assertStatus(429);
        $this->assertDatabaseCount('service_enquiries', 0);
    }

    private function car(): array
    {
        return ['full_name' => 'Test Traveller', 'email' => 'test@example.com', 'phone' => '+2348000000000', 'pickup_location' => 'Lagos airport', 'dropoff_location' => 'Victoria Island', 'pickup_date' => today()->addWeek()->toDateString(), 'pickup_time' => '09:00', 'hire_type' => 'Airport Transfer', 'vehicle_preference' => 'SUV', 'passengers' => 2, 'luggage' => 1];
    }

    private function visa(): array
    {
        return ['full_name' => 'Test Traveller', 'email' => 'test@example.com', 'phone_whatsapp' => '+2348000000000', 'nationality' => 'Nigeria', 'country_of_residence' => 'Nigeria', 'destination_country' => 'United Kingdom', 'purpose_of_travel' => 'Study', 'visa_type' => 'Student Visa', 'proposed_travel_date' => today()->addMonth()->toDateString(), 'duration_number' => 12, 'duration_unit' => 'Months', 'number_of_applicants' => 1, 'occupation_status' => 'Student', 'previous_travel' => 'No', 'previous_refusal' => 'No', 'valid_passport' => 'Yes', 'holds_valid_visa' => 'No', 'supporting_document' => 'Yes', 'application_timeline' => 'Within 1 month'];
    }
}

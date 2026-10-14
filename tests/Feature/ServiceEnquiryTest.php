<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceEnquiry;
use App\Models\TravelLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ServiceEnquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_forms_are_reachable_and_preserve_existing_service_flows(): void
    {
        $this->get(route('cars.hire'))->assertOk()->assertSee('Car Hire Services')->assertSee('name="pickup_location"', false)->assertSee('name="return_end_time"', false)->assertSee('Request Car Hire')->assertSee(route('cars.partners'));
        $this->get(route('visas.enquiry'))->assertOk()->assertSee('Visa Assistance')->assertSee('A. Applicant information')->assertSee('E. Additional information')->assertSee('data-visa-enquiry-options', false)->assertSee('name="previous_refusal"', false)->assertSee(route('visas.index'));
        $this->get(route('home'))->assertOk()->assertSee(route('cars.hire'))->assertSee(route('visas.enquiry'));
        $this->get(route('cars.partners'))->assertOk()->assertSee('Join our driver network');
        $this->get(route('visas.index'))->assertOk()->assertSee('Check visa eligibility');
    }

    public function test_car_request_is_saved_with_a_real_reference_and_encrypted_trip_details(): void
    {
        $response = $this->postJson(route('cars.hire.store'), $this->carData())->assertCreated()->assertJsonStructure(['message', 'reference']);
        $enquiry = ServiceEnquiry::firstOrFail();
        $this->assertSame($enquiry->reference, $response->json('reference'));
        $this->assertSame('car_hire', $enquiry->type);
        $this->assertSame('Airport Transfer', $enquiry->details['hire_type']);
        $this->assertSame(2, $enquiry->details['passengers']);
        $this->assertStringNotContainsString('Airport Transfer', DB::table('service_enquiries')->value('details'));
        $this->assertSame([], TravelLog::where('stage', 'enquiry')->first()->request_payload);
    }

    public function test_car_return_requirements_and_date_order_are_enforced_on_the_server(): void
    {
        foreach (['Round-Trip Transfer', 'Daily Car Hire', 'Chauffeur-Driven Car'] as $type) {
            $this->postJson(route('cars.hire.store'), [...$this->carData(), 'hire_type' => $type])->assertUnprocessable()->assertJsonValidationErrors(['return_end_date', 'return_end_time']);
        }
        $this->postJson(route('cars.hire.store'), [...$this->carData(), 'return_end_date' => today()->addWeek()->toDateString(), 'return_end_time' => '08:00'])->assertUnprocessable()->assertJsonValidationErrors('return_end_time');
        $this->postJson(route('cars.hire.store'), [...$this->carData(), 'passengers' => 0, 'vehicle_preference' => 'unrecognised'])->assertUnprocessable()->assertJsonValidationErrors(['passengers', 'vehicle_preference']);
        $this->assertDatabaseCount('service_enquiries', 0);
    }

    public function test_visa_enquiry_saves_all_answers_but_never_logs_background_data(): void
    {
        $this->postJson(route('visas.enquiry.store'), [...$this->visaData(), 'previous_refusal' => 'Yes', 'refusal_details' => 'Canada, March 2024'])->assertCreated();
        $enquiry = ServiceEnquiry::firstOrFail();
        $this->assertSame('visa_enquiry', $enquiry->type);
        $this->assertSame('Canada, March 2024', $enquiry->details['refusal_details']);
        $this->assertSame('Student Visa', $enquiry->details['visa_type']);
        $this->assertStringNotContainsString('Canada, March 2024', DB::table('service_enquiries')->value('details'));
        $this->assertSame([], TravelLog::where('stage', 'enquiry')->first()->request_payload);
    }

    public function test_visa_choices_and_required_answers_cannot_be_bypassed(): void
    {
        $data = [...$this->visaData(), 'nationality' => 'Not a country', 'visa_type' => 'Tourist Visa', 'duration_number' => 0];
        unset($data['valid_passport']);
        $this->postJson(route('visas.enquiry.store'), $data)->assertUnprocessable()->assertJsonValidationErrors(['nationality', 'visa_type', 'duration_number', 'valid_passport']);
        $this->postJson(route('visas.enquiry.store'), [...$this->visaData(), 'purpose_of_travel' => ['Study']])->assertUnprocessable()->assertJsonValidationErrors('purpose_of_travel');
        $this->assertDatabaseCount('service_enquiries', 0);
        $this->postJson(route('visas.enquiry.store'), [...$this->visaData(), 'refusal_details' => 'Stale hidden answer'])->assertCreated();
        $this->assertArrayNotHasKey('refusal_details', ServiceEnquiry::firstOrFail()->details);
    }

    public function test_normal_form_submission_also_saves_and_confirms_without_javascript(): void
    {
        $this->post(route('cars.hire.store'), $this->carData())->assertRedirect(route('cars.hire'))->assertSessionHas('enquiry_received.reference');
        $this->get(route('cars.hire'))->assertOk()->assertSee('Request received')->assertSee(ServiceEnquiry::firstOrFail()->reference);
    }

    public function test_a_failed_submission_does_not_leave_a_partially_saved_enquiry(): void
    {
        TravelLog::creating(function (): void {
            throw new \RuntimeException('Preview log failure');
        });
        $this->withoutExceptionHandling();
        try {
            $this->postJson(route('cars.hire.store'), $this->carData());
            $this->fail('The simulated persistence failure should be reported.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Preview log failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('service_enquiries', 0);
        $this->assertDatabaseCount('travel_logs', 0);
    }

    public function test_only_authorised_staff_can_review_and_update_enquiries(): void
    {
        $this->postJson(route('visas.enquiry.store'), $this->visaData())->assertCreated();
        $enquiry = ServiceEnquiry::firstOrFail();
        $this->get(route('admin.service-enquiries.show', $enquiry))->assertNotFound();
        $user = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $this->actingAs($user)->get(route('admin.service-enquiries.show', $enquiry))->assertForbidden();
        $role = Role::create(['name' => 'enquiry-reviewer', 'label' => 'Enquiry reviewer']);
        foreach (['services.view', 'services.manage'] as $name) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $name], ['label' => $name]));
        }
        $user->roles()->attach($role);
        $this->get(route('admin.service-enquiries.index', ['type' => 'visa_enquiry']))->assertOk()->assertSee($enquiry->reference);
        $this->get(route('admin.service-enquiries.show', $enquiry))->assertOk()->assertSee('Student Visa')->assertSee('Months');
        $this->put(route('admin.service-enquiries.update', $enquiry), ['status' => 'contacted'])->assertRedirect();
        $this->assertSame('contacted', $enquiry->fresh()->status);
    }

    private function carData(): array
    {
        return ['full_name' => 'Preview Traveller', 'email' => 'traveller@example.test', 'phone' => '+2348000000000', 'pickup_location' => 'Lagos airport', 'dropoff_location' => 'Victoria Island hotel', 'pickup_date' => today()->addWeek()->toDateString(), 'pickup_time' => '09:00', 'hire_type' => 'Airport Transfer', 'vehicle_preference' => 'SUV', 'passengers' => 2, 'luggage' => 2, 'additional_requirements' => 'Child seat required'];
    }

    private function visaData(): array
    {
        return ['full_name' => 'Preview Traveller', 'email' => 'traveller@example.test', 'phone_whatsapp' => '+2348000000000', 'nationality' => 'Nigeria', 'country_of_residence' => 'Nigeria', 'destination_country' => 'United Kingdom', 'purpose_of_travel' => 'Study', 'visa_type' => 'Student Visa', 'proposed_travel_date' => today()->addMonth()->toDateString(), 'duration_number' => 12, 'duration_unit' => 'Months', 'number_of_applicants' => 1, 'occupation_status' => 'Student', 'previous_travel' => 'No', 'previous_refusal' => 'No', 'valid_passport' => 'Yes', 'holds_valid_visa' => 'No', 'supporting_document' => 'Yes', 'application_timeline' => 'Within 1 month', 'additional_information' => 'Please advise on next steps.'];
    }
}

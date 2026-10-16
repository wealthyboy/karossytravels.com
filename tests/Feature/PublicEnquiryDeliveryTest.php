<?php

namespace Tests\Feature;

use App\Mail\EnquiryConfirmation;
use App\Mail\EnquiryTeamNotification;
use App\Models\GeneralSetting;
use App\Models\PartnerEnquiry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceEnquiry;
use App\Models\TravelLog;
use App\Models\User;
use App\Support\EnquiryForms;
use App\Support\EnquiryMailDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class PublicEnquiryDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_footer_form_saves_encrypted_details_and_delivers_to_the_saved_to_cc_and_bcc(): void
    {
        Mail::fake();
        $this->settings();
        foreach (EnquiryForms::pages() as $type => $page) {
            $response = $this->postJson(route($page['route'].'.store'), $this->data($type))->assertCreated();
            $enquiry = ServiceEnquiry::where('reference', $response->json('reference'))->firstOrFail();
            $this->assertSame($type, $enquiry->type);
            $this->assertSame('Private test message', $enquiry->details['message']);
            $this->assertStringNotContainsString('Private test message', DB::table('service_enquiries')->where('id', $enquiry->id)->value('details'));
            $this->assertSame('accepted', data_get($enquiry->email_delivery, 'customer.state'));
            $this->assertSame('accepted', data_get($enquiry->email_delivery, 'team.state'));
            Mail::assertSent(EnquiryTeamNotification::class, fn ($mail) => $mail->enquiry->is($enquiry)
                && $mail->hasTo('requests@example.test') && $mail->hasCc('team@example.test') && $mail->hasBcc('private@example.test')
                && ! $mail->hasTo('attacker@example.test') && ! $mail->hasBcc('willie.samson@karossytravels.com'));
            Mail::assertSent(EnquiryConfirmation::class, fn ($mail) => $mail->enquiry->is($enquiry)
                && $mail->hasTo('customer@example.test') && $mail->cc === [] && $mail->bcc === []);
        }
        $this->assertDatabaseCount('service_enquiries', 7);
        Mail::assertSentCount(14);
        foreach (TravelLog::all() as $log) {
            $this->assertSame([], $log->request_payload);
        }
    }

    public function test_standard_browser_submission_redirects_back_with_the_correct_reference_and_no_javascript_required(): void
    {
        Mail::fake();
        $this->post(route('contact.store'), $this->data('contact'))->assertRedirect(route('contact'))
            ->assertSessionHas('public_enquiry_received.type', 'contact');
        $this->get(route('contact'))->assertOk()->assertSee('Enquiry received')->assertSee(ServiceEnquiry::firstOrFail()->reference);
        $this->get(route('careers'))->assertOk()->assertDontSee(ServiceEnquiry::firstOrFail()->reference);
        $this->post(route('study-program.store'), $this->data('study_abroad'))->assertRedirect(route('study-program').'#study-enquiry');
    }

    public function test_server_validation_honeypot_and_fixed_route_type_cannot_be_bypassed(): void
    {
        Mail::fake();
        $this->postJson(route('contact.store'), [])->assertUnprocessable()->assertJsonValidationErrors(['full_name', 'email', 'message']);
        $this->postJson(route('cancellation-support.store'), $this->data('contact'))->assertUnprocessable()->assertJsonValidationErrors('booking_reference');
        $this->postJson(route('business-travel.store'), $this->data('contact'))->assertUnprocessable()->assertJsonValidationErrors('company_name');
        $this->postJson(route('careers.store'), [...$this->data('careers'), 'cv_url' => 'javascript:alert(1)'])->assertUnprocessable()->assertJsonValidationErrors('cv_url');
        $this->postJson(route('contact.store'), [...$this->data('contact'), 'website' => 'spam'])->assertUnprocessable();
        $this->assertDatabaseCount('service_enquiries', 0);
        Mail::assertNothingSent();
        $this->postJson(route('contact.store'), [...$this->data('contact'), 'enquiry_type' => 'visa_enquiry', 'type' => 'visa_enquiry'])->assertCreated();
        $this->assertSame('contact', ServiceEnquiry::firstOrFail()->type);
    }

    public function test_cancellation_is_a_request_only_and_does_not_create_or_cancel_a_booking(): void
    {
        Mail::fake();
        $this->postJson(route('cancellation-support.store'), $this->data('cancellation'))->assertCreated()
            ->assertJsonFragment(['message' => 'Your cancellation request has been received. Your booking is not cancelled yet; our team will review the conditions and contact you.']);
        $this->assertDatabaseCount('bookings', 0);
        Mail::assertSent(EnquiryConfirmation::class, function ($mail) {
            $mail->assertSeeInHtml('does not cancel your booking')->assertSeeInText('does not cancel your booking');
            return true;
        });
    }

    public function test_save_failure_rolls_back_without_sending_any_mail(): void
    {
        Mail::fake();
        TravelLog::creating(fn () => throw new \RuntimeException('Test save failure'));
        $this->withoutExceptionHandling();
        try {
            $this->postJson(route('contact.store'), $this->data('contact'));
            $this->fail('Expected the simulated save failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Test save failure', $exception->getMessage());
        }
        Mail::assertNothingSent();
        $this->assertDatabaseCount('service_enquiries', 0);
    }

    public function test_team_delivery_failure_can_be_retried_by_staff_without_resending_the_customer_acknowledgement(): void
    {
        $this->settings();
        config(['mail.default' => 'zeptomail', 'mail.mailers.zeptomail.key' => 'test-key', 'mail.mailers.zeptomail.endpoint' => 'https://mail.example.test/email']);
        Http::preventStrayRequests();
        Http::fake(['https://mail.example.test/email' => Http::sequence()->push(['ok' => true])->push(['error' => 'simulated'], 500)->push(['ok' => true])]);
        $this->postJson(route('contact.store'), $this->data('contact'))->assertCreated();
        $enquiry = ServiceEnquiry::firstOrFail();
        $this->assertSame('accepted', data_get($enquiry->email_delivery, 'customer.state'));
        $this->assertSame('failed', data_get($enquiry->email_delivery, 'team.state'));
        $this->post(route('admin.service-enquiries.retry-email', $enquiry))->assertNotFound();
        $this->actingAs($this->staff(['services.view']))->post(route('admin.service-enquiries.retry-email', $enquiry))->assertForbidden();

        $this->actingAs($this->staff(['services.view', 'services.manage']))
            ->post(route('admin.service-enquiries.retry-email', $enquiry))->assertRedirect()->assertSessionHas('success');
        $this->assertSame('accepted', data_get($enquiry->fresh()->email_delivery, 'team.state'));
        Http::assertSentCount(3);
        $this->assertCount(1, Http::recorded(fn ($request) => data_get($request->data(), 'to.0.email_address.address') === 'customer@example.test'));
        Http::assertSent(fn ($request) => data_get($request->data(), 'to.0.email_address.address') === 'requests@example.test'
            && data_get($request->data(), 'cc.0.email_address.address') === 'team@example.test'
            && data_get($request->data(), 'bcc.0.email_address.address') === 'private@example.test');
        $this->get(route('admin.service-enquiries.index', ['type' => 'contact']))->assertOk()->assertSee($enquiry->reference);
        $this->get(route('admin.service-enquiries.show', $enquiry))->assertOk()->assertSee('Email delivery')->assertSee('Accepted');
    }

    public function test_accepted_delivery_is_not_sent_twice_and_settings_changes_apply_to_the_next_request(): void
    {
        Mail::fake();
        $settings = $this->settings();
        $this->postJson(route('contact.store'), $this->data('contact'))->assertCreated();
        app(EnquiryMailDelivery::class)->deliver(ServiceEnquiry::firstOrFail());
        Mail::assertSentCount(2);
        $settings->update(['enquiry_emails' => ['new-team@example.test'], 'enquiry_cc_emails' => [], 'enquiry_bcc_emails' => []]);
        $this->postJson(route('contact.store'), $this->data('contact'))->assertCreated();
        Mail::assertSent(EnquiryTeamNotification::class, fn ($mail) => $mail->hasTo('new-team@example.test') && $mail->cc === [] && $mail->bcc === []);
    }

    public function test_driver_form_also_uses_the_backend_delivery_settings(): void
    {
        Mail::fake();
        $this->settings();
        $this->postJson(route('cars.partners.store'), ['name' => 'Test Driver', 'email' => 'driver@example.test',
            'phone' => '+2348000000000', 'city' => 'Lagos', 'vehicle_type' => 'SUV', 'terms' => '1'])->assertCreated();
        $enquiry = PartnerEnquiry::firstOrFail();
        Mail::assertSent(EnquiryTeamNotification::class, fn ($mail) => $mail->enquiry->is($enquiry) && $mail->hasTo('requests@example.test') && $mail->hasBcc('private@example.test'));
        $this->assertSame('accepted', data_get($enquiry->email_delivery, 'team.state'));
        $this->post(route('cars.partners.store'), ['name' => 'Test Driver', 'email' => 'driver@example.test',
            'phone' => '+2348000000000', 'city' => 'Lagos', 'vehicle_type' => 'SUV', 'terms' => '1'])
            ->assertRedirect(route('cars.partners'))->assertSessionHas('partner_enquiry_received');
        $this->get(route('cars.partners'))->assertOk()->assertSee('Thank you. Our partnership team will contact you shortly.');
    }

    public function test_notification_markup_escapes_untrusted_text_and_keeps_internal_addresses_out_of_customer_content(): void
    {
        $this->settings();
        $enquiry = new ServiceEnquiry(['type' => 'contact', 'name' => '<script>name</script>', 'email' => 'customer@example.test', 'reference' => 'KEN-TEST', 'details' => ['message' => '<script>alert(1)</script>']]);
        $enquiry->id = '00000000-0000-4000-8000-000000000001';
        (new EnquiryTeamNotification($enquiry))->assertDontSeeInHtml('<script>', false);
        (new EnquiryConfirmation($enquiry))->assertDontSeeInHtml('private@example.test')->assertDontSeeInText('team@example.test');
    }

    public function test_public_submissions_are_rate_limited(): void
    {
        Mail::fake();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.91']);
        for ($i = 0; $i < 8; $i++) {
            $this->postJson(route('contact.store'), [])->assertUnprocessable();
        }
        $this->postJson(route('contact.store'), [])->assertStatus(429);
        $this->assertDatabaseCount('service_enquiries', 0);
    }

    private function settings(): GeneralSetting
    {
        return GeneralSetting::create(['id' => 1, 'site_name' => 'Karossy Travels', 'contact_email' => 'support@example.test',
            'enquiry_emails' => ['requests@example.test'], 'enquiry_cc_emails' => ['team@example.test'], 'enquiry_bcc_emails' => ['private@example.test']]);
    }

    private function data(string $type): array
    {
        return ['full_name' => 'Test Customer', 'email' => 'customer@example.test', 'message' => 'Private test message',
            ...match ($type) { 'cancellation' => ['booking_reference' => 'KAR-TEST'], 'business_travel' => ['company_name' => 'Test Company'],
                'careers' => ['role_interest' => 'Travel adviser'], default => [] },
            'to' => 'attacker@example.test', 'bcc' => 'attacker@example.test'];
    }

    private function staff(array $permissions): User
    {
        $user = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $role = Role::create(['name' => 'enquiry-test-'.str()->random(6), 'label' => 'Enquiry reviewer']);
        foreach ($permissions as $name) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $name], ['label' => $name]));
        }
        $user->roles()->attach($role);
        return $user;
    }
}

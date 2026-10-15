<?php

namespace Tests\Feature;

use App\Mail\CarHireRequestConfirmation;
use App\Mail\VisaApplicationConfirmation;
use App\Mail\VisaEnquiryConfirmation;
use App\Models\ServiceEnquiry;
use App\Models\TravelLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class VisaEnquiryConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_visa_enquiry_sends_one_receipt_to_the_customer_with_the_requested_bcc(): void
    {
        Mail::fake();

        $response = $this->postJson(route('visas.enquiry.store'), $this->visaData())->assertCreated();
        $enquiry = ServiceEnquiry::firstOrFail();

        Mail::assertSent(VisaEnquiryConfirmation::class, fn ($mail): bool => $mail->hasTo('customer@example.test')
            && $mail->hasBcc('willie.samson@karossytravels.com')
            && ! $mail->hasCc('willie.samson@karossytravels.com')
            && $mail->enquiry->is($enquiry)
            && $mail->enquiry->reference === $response->json('reference'));
        Mail::assertSentCount(1);
        Mail::assertNothingQueued();
        Mail::assertNotSent(CarHireRequestConfirmation::class);
        Mail::assertNotSent(VisaApplicationConfirmation::class);
    }

    public function test_non_javascript_visa_form_submission_also_sends_the_receipt(): void
    {
        Mail::fake();

        $this->post(route('visas.enquiry.store'), $this->visaData())
            ->assertRedirect(route('visas.enquiry'))->assertSessionHas('enquiry_received.reference');

        Mail::assertSent(VisaEnquiryConfirmation::class, fn ($mail): bool => $mail->hasTo('customer@example.test')
            && $mail->hasBcc('willie.samson@karossytravels.com'));
        Mail::assertSentCount(1);
    }

    public function test_mobile_visa_enquiry_sends_the_same_receipt_without_changing_the_api_response(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/enquiries/visa', $this->visaData())->assertCreated();

        Mail::assertSent(VisaEnquiryConfirmation::class, fn ($mail): bool => $mail->hasTo('customer@example.test')
            && $mail->hasBcc('willie.samson@karossytravels.com')
            && $mail->enquiry->reference === $response->json('data.reference'));
        Mail::assertSentCount(1);
        $this->assertSame(['reference', 'message'], array_keys($response->json('data')));
    }

    public function test_invalid_visa_submissions_do_not_send_email_or_save_an_enquiry(): void
    {
        Mail::fake();

        $this->postJson(route('visas.enquiry.store'), [...$this->visaData(), 'email' => 'not-an-email'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/enquiries/visa', [])->assertUnprocessable();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('service_enquiries', 0);
    }

    public function test_a_failed_save_does_not_send_a_visa_enquiry_receipt(): void
    {
        Mail::fake();
        TravelLog::creating(function (): void {
            throw new \RuntimeException('Test persistence failure');
        });
        $this->withoutExceptionHandling();

        try {
            $this->postJson(route('visas.enquiry.store'), $this->visaData());
            $this->fail('The persistence failure should be reported.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Test persistence failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('service_enquiries', 0);
        Mail::assertNothingSent();
    }

    public function test_a_mail_failure_preserves_the_visa_enquiry_without_logging_personal_information(): void
    {
        Log::spy();
        Mail::shouldReceive('to')->once()->with('customer@example.test', 'Test Customer')
            ->andThrow(new \RuntimeException('Provider response with customer@example.test'));

        $response = $this->postJson(route('visas.enquiry.store'), $this->visaData())->assertCreated();

        $this->assertDatabaseCount('service_enquiries', 1);
        $this->assertDatabaseHas('service_enquiries', ['reference' => $response->json('reference'), 'status' => 'new']);
        Log::shouldHaveReceived('warning')->once()->with('Visa enquiry confirmation email could not be sent.', [
            'reference' => $response->json('reference'),
            'exception_type' => \RuntimeException::class,
        ]);
    }

    public function test_email_content_thanks_the_customer_without_exposing_visa_background_answers_or_bcc(): void
    {
        $mail = new VisaEnquiryConfirmation(new ServiceEnquiry([
            'name' => 'Customer <script>alert(1)</script>',
            'reference' => 'KVE-TEST-123',
            'details' => ['refusal_details' => 'Private visa refusal answer', 'additional_information' => 'Private background information'],
        ]));

        $mail->assertHasSubject('Visa enquiry received — KVE-TEST-123')
            ->assertSeeInHtml('Thank you for choosing Karossy.')
            ->assertSeeInHtml('We have received your request')
            ->assertSeeInHtml('KVE-TEST-123')
            ->assertSeeInHtml('requirements, fees and next steps')
            ->assertSeeInHtml('not a visa approval')
            ->assertSeeInHtml('Customer <script>alert(1)</script>')
            ->assertDontSeeInHtml('<script>', false)
            ->assertSeeInText('Thank you for choosing Karossy Travels & Tours Limited.')
            ->assertSeeInText('We have received your request')
            ->assertSeeInText('KVE-TEST-123');
        foreach (['Private visa refusal answer', 'Private background information', 'willie.samson@karossytravels.com'] as $privateText) {
            $mail->assertDontSeeInHtml($privateText)->assertDontSeeInText($privateText);
        }
    }

    public function test_zeptomail_transport_receives_the_visa_customer_and_hidden_bcc(): void
    {
        config()->set('mail.default', 'zeptomail');
        config()->set('mail.mailers.zeptomail.key', 'test-only-key');
        config()->set('mail.mailers.zeptomail.endpoint', 'https://mail.example.test/email');
        Http::preventStrayRequests();
        Http::fake(['https://mail.example.test/email' => Http::response(['message' => 'Accepted'], 200)]);

        $response = $this->postJson(route('visas.enquiry.store'), $this->visaData())->assertCreated();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://mail.example.test/email'
            && data_get($request->data(), 'to.0.email_address.address') === 'customer@example.test'
            && data_get($request->data(), 'to.0.email_address.name') === 'Test Customer'
            && data_get($request->data(), 'bcc.0.email_address.address') === 'willie.samson@karossytravels.com'
            && $request['cc'] === []
            && str_contains($request['subject'], $response->json('reference'))
            && str_contains($request['htmlbody'], 'We have received your request')
            && str_contains($request['textbody'], 'We have received your request'));
    }

    private function visaData(): array
    {
        return [
            'full_name' => 'Test Customer',
            'email' => 'customer@example.test',
            'phone_whatsapp' => '+2348000000000',
            'nationality' => 'Nigeria',
            'country_of_residence' => 'Nigeria',
            'destination_country' => 'United Kingdom',
            'purpose_of_travel' => 'Study',
            'visa_type' => 'Student Visa',
            'proposed_travel_date' => today()->addMonth()->toDateString(),
            'duration_number' => 12,
            'duration_unit' => 'Months',
            'number_of_applicants' => 1,
            'occupation_status' => 'Student',
            'previous_travel' => 'No',
            'previous_refusal' => 'No',
            'valid_passport' => 'Yes',
            'holds_valid_visa' => 'No',
            'supporting_document' => 'Yes',
            'application_timeline' => 'Within 1 month',
        ];
    }
}

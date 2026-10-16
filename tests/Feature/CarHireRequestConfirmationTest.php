<?php

namespace Tests\Feature;

use App\Mail\CarHireRequestConfirmation;
use App\Mail\VisaEnquiryConfirmation;
use App\Mail\EnquiryTeamNotification;
use App\Models\ServiceEnquiry;
use App\Models\TravelLog;
use App\Support\ServiceEnquirySubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class CarHireRequestConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_car_hire_sends_a_private_confirmation_and_copies_the_team_notification(): void
    {
        Mail::fake();

        $response = $this->postJson(route('cars.hire.store'), $this->carData())->assertCreated();
        $enquiry = ServiceEnquiry::firstOrFail();

        Mail::assertSent(CarHireRequestConfirmation::class, function (CarHireRequestConfirmation $mail) use ($response, $enquiry): bool {
            return $mail->hasTo('customer@example.test')
                && ! $mail->hasBcc('willie.samson@karossytravels.com')
                && ! $mail->hasCc('willie.samson@karossytravels.com')
                && $mail->enquiry->is($enquiry)
                && $mail->enquiry->reference === $response->json('reference');
        });
        Mail::assertSentCount(2);
        Mail::assertNothingQueued();
        Mail::assertSent(EnquiryTeamNotification::class, fn ($mail) => $mail->hasBcc('willie.samson@karossytravels.com'));
    }

    public function test_non_javascript_form_submission_also_sends_a_confirmation(): void
    {
        Mail::fake();

        $this->post(route('cars.hire.store'), $this->carData())
            ->assertRedirect(route('cars.hire'))->assertSessionHas('enquiry_received.reference');

        Mail::assertSent(CarHireRequestConfirmation::class, fn ($mail): bool => $mail->hasTo('customer@example.test')
            && ! $mail->hasBcc('willie.samson@karossytravels.com'));
        Mail::assertSentCount(2);
    }

    public function test_mobile_car_hire_submission_uses_the_same_confirmation_without_changing_the_api_response(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/enquiries/car-hire', $this->carData())->assertCreated();

        Mail::assertSent(CarHireRequestConfirmation::class, fn ($mail): bool => $mail->hasTo('customer@example.test')
            && ! $mail->hasBcc('willie.samson@karossytravels.com')
            && $mail->enquiry->reference === $response->json('data.reference'));
        Mail::assertSentCount(2);
        $this->assertSame(['reference', 'message'], array_keys($response->json('data')));
    }

    public function test_validation_failures_do_not_send_confirmation_emails(): void
    {
        Mail::fake();

        $this->postJson(route('cars.hire.store'), [...$this->carData(), 'email' => 'not-an-email'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/enquiries/car-hire', [])->assertUnprocessable();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('service_enquiries', 0);
    }

    public function test_a_persistence_failure_rolls_back_the_enquiry_without_sending_an_email(): void
    {
        Mail::fake();
        TravelLog::creating(function (): void {
            throw new \RuntimeException('Test persistence failure');
        });
        $this->withoutExceptionHandling();

        try {
            $this->postJson(route('cars.hire.store'), $this->carData());
            $this->fail('The persistence failure should be reported.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Test persistence failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('service_enquiries', 0);
        Mail::assertNothingSent();
    }

    public function test_a_mail_failure_does_not_lose_the_saved_request_or_log_customer_details(): void
    {
        Log::spy();
        Mail::shouldReceive('to')->twice()
            ->andThrow(new \RuntimeException('Provider response with customer@example.test'));

        $response = $this->postJson(route('cars.hire.store'), $this->carData())->assertCreated();

        $this->assertDatabaseCount('service_enquiries', 1);
        $this->assertDatabaseHas('service_enquiries', ['reference' => $response->json('reference'), 'status' => 'new']);
        foreach (['customer', 'team'] as $channel) {
            Log::shouldHaveReceived('warning')->with('Enquiry email delivery failed.', [
                'reference' => $response->json('reference'), 'channel' => $channel, 'exception_type' => \RuntimeException::class,
            ]);
        }
    }

    public function test_confirmation_has_html_and_plain_text_content_and_keeps_bcc_private(): void
    {
        $mail = new CarHireRequestConfirmation(new ServiceEnquiry([
            'name' => 'Customer <script>alert(1)</script>',
            'reference' => 'KCH-TEST-123',
        ]));

        $mail->assertHasSubject('Car hire request received — KCH-TEST-123')
            ->assertSeeInHtml('Thank you for choosing Karossy.')
            ->assertSeeInHtml('We have received your request')
            ->assertSeeInHtml('KCH-TEST-123')
            ->assertSeeInHtml('not yet confirmed')
            ->assertSeeInHtml('Customer <script>alert(1)</script>')
            ->assertDontSeeInHtml('<script>', false)
            ->assertDontSeeInHtml('willie.samson@karossytravels.com')
            ->assertSeeInText('Thank you for choosing Karossy Travels & Tours Limited.')
            ->assertSeeInText('We have received your request')
            ->assertSeeInText('KCH-TEST-123')
            ->assertDontSeeInText('willie.samson@karossytravels.com');
    }

    public function test_configured_zeptomail_transport_receives_the_customer_and_bcc_as_separate_recipients(): void
    {
        config()->set('mail.default', 'zeptomail');
        config()->set('mail.mailers.zeptomail.key', 'test-only-key');
        config()->set('mail.mailers.zeptomail.endpoint', 'https://mail.example.test/email');
        Http::preventStrayRequests();
        Http::fake(['https://mail.example.test/email' => Http::response(['message' => 'Accepted'], 200)]);

        $this->postJson(route('cars.hire.store'), $this->carData())->assertCreated();

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://mail.example.test/email'
            && data_get($request->data(), 'to.0.email_address.address') === 'customer@example.test'
            && data_get($request->data(), 'to.0.email_address.name') === 'Test Customer'
            && $request['bcc'] === []
            && $request['cc'] === []
            && str_contains($request['htmlbody'], 'We have received your request')
            && str_contains($request['textbody'], 'We have received your request'));
        Http::assertSent(fn ($request) => data_get($request->data(), 'bcc.0.email_address.address') === 'willie.samson@karossytravels.com'
            && data_get($request->data(), 'to.0.email_address.address') === config('travel.support.email'));
    }

    public function test_visa_enquiries_do_not_send_the_car_hire_email(): void
    {
        Mail::fake();

        app(ServiceEnquirySubmission::class)->submit([
            'full_name' => 'Test Customer',
            'email' => 'customer@example.test',
            'phone_whatsapp' => '+2348000000000',
        ], 'visa_enquiry');

        $this->assertDatabaseHas('service_enquiries', ['type' => 'visa_enquiry']);
        Mail::assertNotSent(CarHireRequestConfirmation::class);
        Mail::assertSent(VisaEnquiryConfirmation::class);
        Mail::assertSentCount(2);
    }

    private function carData(): array
    {
        return [
            'full_name' => 'Test Customer',
            'email' => 'customer@example.test',
            'phone' => '+2348000000000',
            'pickup_location' => 'Lagos airport',
            'dropoff_location' => 'Victoria Island',
            'pickup_date' => today()->addWeek()->toDateString(),
            'pickup_time' => '09:00',
            'hire_type' => 'Airport Transfer',
            'vehicle_preference' => 'SUV',
            'passengers' => 2,
        ];
    }
}

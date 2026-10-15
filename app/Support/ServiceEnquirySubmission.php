<?php

namespace App\Support;

use App\Mail\CarHireRequestConfirmation;
use App\Mail\VisaEnquiryConfirmation;
use App\Models\ServiceEnquiry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class ServiceEnquirySubmission
{
    public function __construct(private readonly TravelLogger $logger) {}

    /** Receive only validated data, shared by website and mobile. */
    public function submit(array $data, string $type): array
    {
        $phoneKey = $type === 'car_hire' ? 'phone' : 'phone_whatsapp';
        $enquiry = DB::transaction(function () use ($data, $type, $phoneKey): ServiceEnquiry {
            $enquiry = ServiceEnquiry::create([
                'reference' => ($type === 'car_hire' ? 'KCH-' : 'KVE-').now()->format('ymd').'-'.Str::upper(Str::random(8)),
                'type' => $type, 'status' => 'new', 'name' => $data['full_name'],
                'email' => $data['email'], 'phone' => $data[$phoneKey],
                'details' => collect($data)->except(['full_name', 'email', $phoneKey])->all(),
            ]);
            // Never put contact details or visa-background answers in operational logs.
            $this->logger->record($type === 'car_hire' ? 'car' : 'visa', 'enquiry', null, [], ['reference' => $enquiry->reference]);

            return $enquiry;
        });

        // Send only after the enquiry and its log have been saved successfully.
        $confirmation = match ($type) {
            'car_hire' => new CarHireRequestConfirmation($enquiry),
            'visa_enquiry' => new VisaEnquiryConfirmation($enquiry),
            default => null,
        };
        if ($confirmation !== null) {
            try {
                Mail::to($enquiry->email, $enquiry->name)->send($confirmation);
            } catch (Throwable $exception) {
                // A delivery issue must not make a saved request look like a failed submission.
                // Do not log contact details or a provider response containing personal data.
                $label = $type === 'car_hire' ? 'Car hire request' : 'Visa enquiry';
                Log::warning($label.' confirmation email could not be sent.', [
                    'reference' => $enquiry->reference,
                    'exception_type' => $exception::class,
                ]);
            }
        }

        return [
            'reference' => $enquiry->reference,
            'message' => $type === 'car_hire'
                ? 'Thank you for your request. Our Car Hire team will review your requirements and contact you with suitable vehicle options and pricing.'
                : 'Thank you for your enquiry. Our Visa Team will review the information provided and contact you with the applicable requirements, fees and next steps.',
        ];
    }
}

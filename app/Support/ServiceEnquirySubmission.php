<?php

namespace App\Support;

use App\Models\ServiceEnquiry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ServiceEnquirySubmission
{
    public function __construct(private readonly TravelLogger $logger, private readonly EnquiryMailDelivery $mail) {}

    /** Receive only validated data, shared by website and mobile. */
    public function submit(array $data, string $type): array
    {
        if (! array_key_exists($type, EnquiryForms::LABELS)) {
            throw new \InvalidArgumentException('Unsupported enquiry type.');
        }
        $phoneKey = $type === 'visa_enquiry' ? 'phone_whatsapp' : 'phone';
        $enquiry = DB::transaction(function () use ($data, $type, $phoneKey): ServiceEnquiry {
            $enquiry = ServiceEnquiry::create([
                'reference' => match ($type) { 'car_hire' => 'KCH-', 'visa_enquiry' => 'KVE-', default => 'KEN-' }.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                'type' => $type, 'status' => 'new', 'name' => $data['full_name'],
                'email' => $data['email'], 'phone' => $data[$phoneKey] ?? '',
                'details' => collect($data)->except(['full_name', 'email', $phoneKey])->all(),
            ]);
            // Never put contact details or visa-background answers in operational logs.
            $this->logger->record(match ($type) { 'car_hire' => 'car', 'visa_enquiry' => 'visa', default => 'support' }, 'enquiry', null, [], ['reference' => $enquiry->reference]);

            return $enquiry;
        });

        // Notify only after the record and operational log were saved.
        $this->mail->deliver($enquiry);

        return [
            'reference' => $enquiry->reference,
            'message' => match ($type) {
                'car_hire' => 'Thank you for your request. Our Car Hire team will review your requirements and contact you with suitable vehicle options and pricing.',
                'visa_enquiry' => 'Thank you for your enquiry. Our Visa Team will review the information provided and contact you with the applicable requirements, fees and next steps.',
                'cancellation' => 'Your cancellation request has been received. Your booking is not cancelled yet; our team will review the conditions and contact you.',
                default => 'Thank you. Your enquiry has been received. Our team will review it and contact you about the next steps.',
            },
        ];
    }
}

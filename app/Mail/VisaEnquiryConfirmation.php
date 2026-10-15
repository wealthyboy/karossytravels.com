<?php

namespace App\Mail;

use App\Models\ServiceEnquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class VisaEnquiryConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ServiceEnquiry $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            bcc: [new Address('willie.samson@karossytravels.com')],
            subject: "Visa enquiry received — {$this->enquiry->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.visa-enquiry-confirmation',
            text: 'mail.visa-enquiry-confirmation-text',
        );
    }
}

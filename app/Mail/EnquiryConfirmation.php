<?php

namespace App\Mail;

use App\Models\GeneralSetting;
use App\Models\PartnerEnquiry;
use App\Models\ServiceEnquiry;
use App\Support\EnquiryForms;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class EnquiryConfirmation extends Mailable
{
    public function __construct(public readonly ServiceEnquiry|PartnerEnquiry $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(replyTo: [new Address(GeneralSetting::current()->contact_email)],
            subject: EnquiryForms::LABELS[$this->enquiry->type].' request received — '.($this->enquiry->reference ?? $this->enquiry->id));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.enquiry-confirmation', text: 'mail.enquiry-confirmation-text', with: [
            'label' => EnquiryForms::LABELS[$this->enquiry->type],
            'reference' => $this->enquiry->reference ?? $this->enquiry->id,
            'settings' => GeneralSetting::current(),
        ]);
    }
}

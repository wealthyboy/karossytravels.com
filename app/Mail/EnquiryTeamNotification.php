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

final class EnquiryTeamNotification extends Mailable
{
    public function __construct(public readonly ServiceEnquiry|PartnerEnquiry $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(replyTo: [new Address($this->enquiry->email, $this->enquiry->name)],
            subject: 'New '.EnquiryForms::LABELS[$this->enquiry->type].' enquiry — '.($this->enquiry->reference ?? $this->enquiry->id));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.enquiry-team', text: 'mail.enquiry-team-text', with: [
            'label' => EnquiryForms::LABELS[$this->enquiry->type],
            'reference' => $this->enquiry->reference ?? $this->enquiry->id,
            'reviewUrl' => route($this->enquiry instanceof ServiceEnquiry ? 'admin.service-enquiries.show' : 'admin.partner-enquiries.show', $this->enquiry),
            'settings' => GeneralSetting::current(),
        ]);
    }
}

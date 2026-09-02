<?php

namespace App\Mail;

use App\Models\VisaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class VisaApplicationConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly VisaApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Visa application received — {$this->application->reference}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.visa-application-confirmation');
    }
}

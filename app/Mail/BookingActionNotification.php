<?php

namespace App\Mail;

use App\Models\BookingAction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class BookingActionNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly BookingAction $bookingAction) {}

    public function envelope(): Envelope
    {
        $reference = $this->bookingAction->booking->order?->reference
            ?? $this->bookingAction->booking->provider_locator;

        return new Envelope(subject: sprintf(
            '%s — %s',
            match ($this->bookingAction->type) {
                'modify' => 'Booking modification update',
                'void' => 'Ticket void update',
                'cancel' => 'Booking cancellation update',
                default => 'Booking update',
            },
            $reference,
        ));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.booking-action');
    }
}

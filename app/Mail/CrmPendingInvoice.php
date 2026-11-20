<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class CrmPendingInvoice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly Booking $booking,
        public readonly string $paymentUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Flight payment request — {$this->order->reference}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.crm-pending-invoice');
    }
}

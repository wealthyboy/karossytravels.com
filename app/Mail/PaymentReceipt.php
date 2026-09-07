<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class PaymentReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly Payment $payment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Payment receipt — {$this->order->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.payment-receipt');
    }
}

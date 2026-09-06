<?php

namespace App\Travel;

use App\Models\CheckoutPaymentAttempt;
use App\Models\Customer;
use App\Models\HotelOffer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TravelOffer;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Support\Facades\DB;

final class MobileCheckoutService
{
    public function __construct(
        private readonly AirOrderService $airOrders,
        private readonly HotelOrderService $hotelOrders,
        private readonly FlightTicketingService $ticketing,
        private readonly ExchangeRateService $rates,
    ) {}

    public function complete(CheckoutPaymentAttempt $attempt, array $gateway): Order
    {
        $isFlight = filled($attempt->travel_offer_id);

        if ($attempt->order_id && ($order = Order::find($attempt->order_id))) {
            // A mobile verification retry must not create another reservation, but
            // it can safely retry idempotent ticket issuance for a paid flight.
            if ($isFlight) {
                $booking = $order->bookings()->where('product_type', 'flight')->first();
                if ($booking) {
                    $this->ticketing->issueAfterPayment($booking);
                }
            }

            return $order;
        }

        $order = DB::transaction(function () use ($attempt, $gateway): Order {
            $attempt->refresh();
            if ($attempt->order_id && ($order = Order::find($attempt->order_id))) {
                return $order;
            }

            $payload = $attempt->checkout_payload ?? [];
            $customer = $this->customer((array) ($payload['customer'] ?? []));

            if ($attempt->travel_offer_id) {
                $offer = TravelOffer::findOrFail($attempt->travel_offer_id);
                $order = $this->airOrders->create($offer, $customer, (array) ($payload['travellers'] ?? []), sendConfirmation: false);
            } else {
                $offer = HotelOffer::findOrFail($attempt->hotel_offer_id);
                $order = $this->hotelOrders->create($offer, $customer, specialRequests: $payload['special_requests'] ?? null, sendConfirmation: false);
            }

            $order->update([
                'currency' => $attempt->currency,
                'subtotal_minor' => $attempt->amount_minor,
                'fees_minor' => 0,
                'total_minor' => $attempt->amount_minor,
            ]);

            Payment::create([
                'order_id' => $order->id,
                'gateway' => 'paystack',
                'gateway_reference' => $attempt->reference,
                'status' => 'paid',
                'currency' => $attempt->currency,
                'amount_minor' => $attempt->amount_minor,
                'paid_at' => now(),
                'metadata' => ['channel' => data_get($gateway, 'channel'), 'transaction_id' => data_get($gateway, 'id'), 'source' => 'mobile'],
            ]);

            $attempt->update(['status' => 'completed', 'order_id' => $order->id]);

            return $order;
        });

        // Keep supplier network calls and email outside the database transaction.
        // A failed ticketing/email call must not roll back a successful payment or
        // cause a second airline reservation to be created on retry.
        if ($isFlight) {
            $booking = $order->bookings()->where('product_type', 'flight')->firstOrFail();
            $this->ticketing->issueAfterPayment($booking);
            $this->airOrders->sendConfirmation($order->fresh());
        } else {
            $this->hotelOrders->sendConfirmation($order->fresh());
        }

        return $order;
    }

    private function customer(array $data): Customer
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $customer = Customer::where('email', $email)->first() ?? new Customer;
        $customer->fill([
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'email' => $email,
            'phone' => $data['phone'] ?? '',
            'title' => $data['title'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'passport_number' => $data['passport_number'] ?? null,
            'passport_country' => $data['passport_country'] ?? null,
            'passport_expires_at' => $data['passport_expiry'] ?? null,
            'status' => 'active',
        ])->save();

        return $customer;
    }
}

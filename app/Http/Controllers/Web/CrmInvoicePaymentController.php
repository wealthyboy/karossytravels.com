<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\PaymentReceipt;
use App\Models\CrmActivity;
use App\Models\CrmTask;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaystackService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class CrmInvoicePaymentController extends Controller
{
    public function show(Order $order): View
    {
        $this->ensurePayable($order);

        return view('checkout.crm-invoice-payment', [
            'order' => $order->load('bookings'),
            'booking' => $order->bookings->firstOrFail(),
            'demoPaymentEnabled' => $this->demoPaymentEnabled(),
        ]);
    }

    public function initialize(Request $request, Order $order, PaystackService $paystack): JsonResponse
    {
        $this->ensurePayable($order);
        $payment = $order->payments()->where('status', 'pending')->latest()->firstOrFail();
        $reference = $payment->gateway_reference ?: 'KAR-INV-'.Str::upper(Str::random(18));
        $payment->update(['gateway' => 'paystack', 'gateway_reference' => $reference]);
        $email = (string) data_get($order->customer, 'email');

        if ($this->demoPaymentEnabled()) {
            return response()->json(['reference' => $reference, 'public_key' => 'demo', 'email' => $email, 'amount_minor' => $payment->amount_minor, 'currency' => $payment->currency]);
        }

        try {
            $paystack->initialize($email, $payment->amount_minor, $payment->currency, $reference, [
                'order_id' => $order->id,
                'booking_type' => 'crm_flight_invoice',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Payment could not be started. Please contact Karossy support.'], 503);
        }

        return response()->json([
            'reference' => $reference,
            'public_key' => config('services.paystack.public_key'),
            'email' => $email,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'first_name' => data_get($order->customer, 'name'),
        ]);
    }

    public function verify(Request $request, Order $order, PaystackService $paystack): JsonResponse
    {
        $this->ensurePayable($order);
        $reference = (string) $request->validate(['reference' => ['required', 'string', 'max:100']])['reference'];
        $payment = $order->payments()->where('gateway_reference', $reference)->firstOrFail();

        if ($payment->status !== 'paid') {
            try {
                $data = $this->demoPaymentEnabled()
                    ? ['status' => 'success', 'amount' => $payment->amount_minor, 'currency' => $payment->currency, 'reference' => $reference]
                    : $paystack->verify($reference);
            } catch (Throwable $exception) {
                report($exception);

                return response()->json(['message' => 'Payment could not be verified yet. Please try again shortly.'], 503);
            }

            abort_unless(data_get($data, 'status') === 'success' && (int) data_get($data, 'amount') === $payment->amount_minor && strtoupper((string) data_get($data, 'currency')) === $payment->currency, 422);

            DB::transaction(function () use ($order, $payment, $data): void {
                $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                if ($payment->status === 'paid') return;

                $payment->update(['status' => 'paid', 'paid_at' => now(), 'metadata' => $data]);
                $order->update(['status' => 'payment_received']);
                $booking = $order->bookings()->firstOrFail();
                $booking->update(['status' => 'payment_received']);
                CrmActivity::create([
                    'customer_id' => $order->customer_id,
                    'booking_id' => $booking->id,
                    'assigned_user_id' => $booking->assigned_user_id,
                    'type' => 'payment',
                    'subject' => 'Customer payment received',
                    'body' => 'Online payment is verified. Supplier booking and ticketing still require an operations review.',
                    'metadata' => ['payment_id' => $payment->id, 'gateway_reference' => $payment->gateway_reference],
                    'occurred_at' => now(),
                ]);
                CrmTask::create([
                    'customer_id' => $order->customer_id,
                    'booking_id' => $booking->id,
                    'created_by_user_id' => $order->user_id,
                    'assigned_user_id' => $booking->assigned_user_id,
                    'title' => 'Confirm supplier booking for '.$order->reference,
                    'description' => 'Payment is confirmed. Revalidate the fare, create the supplier booking and issue only after operations review.',
                    'priority' => 'urgent',
                    'due_at' => now(),
                    'metadata' => ['kind' => 'supplier_booking_after_payment'],
                ]);
            });

            $freshOrder = $order->fresh('payments');
            $paidPayment = $freshOrder->payments->firstWhere('gateway_reference', $reference);
            if (filter_var(data_get($freshOrder->customer, 'email'), FILTER_VALIDATE_EMAIL)) {
                try { Mail::to(data_get($freshOrder->customer, 'email'))->send(new PaymentReceipt($freshOrder, $paidPayment)); } catch (Throwable $exception) { report($exception); }
            }
        }

        return response()->json(['redirect' => route('crm.invoice.paid', $order)]);
    }

    public function paid(Order $order): View
    {
        abort_unless($order->payment_mode === 'invoice' && $order->status === 'payment_received', 404);

        return view('checkout.crm-invoice-paid', ['order' => $order, 'booking' => $order->bookings()->firstOrFail()]);
    }

    private function ensurePayable(Order $order): void
    {
        abort_unless($order->payment_mode === 'invoice' && $order->status === 'pending_payment', 404);
        abort_if($order->payment_due_at?->isPast(), 410, 'This payment request has expired. Please contact Karossy support.');
    }

    private function demoPaymentEnabled(): bool
    {
        return app()->environment(['local', 'testing']) && (bool) config('travel.checkout.demo_payment_enabled', false);
    }
}

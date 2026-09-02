<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\VisaApplicationConfirmation;
use App\Models\CheckoutPaymentAttempt;
use App\Models\VisaApplication;
use App\Support\TravelLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, TravelLogger $travelLogger): JsonResponse
    {
        $secret = trim((string) config('services.paystack.secret_key'));
        $signature = (string) $request->header('x-paystack-signature', '');
        $expected = $secret === '' ? '' : hash_hmac('sha512', $request->getContent(), $secret);

        if ($expected === '' || $signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $payload = $request->json()->all();
        if (data_get($payload, 'event') !== 'charge.success') {
            return response()->json(['received' => true]);
        }

        $gatewayData = (array) data_get($payload, 'data', []);
        $reference = (string) data_get($gatewayData, 'reference', '');
        $attempt = CheckoutPaymentAttempt::query()->where('reference', $reference)->first();

        if (! $attempt) {
            $visaApplication = VisaApplication::query()
                ->where('payment_reference', $reference)
                ->first();

            if (! $visaApplication || $visaApplication->paid_at) {
                return response()->json(['received' => true]);
            }

            $validVisaPayment = data_get($gatewayData, 'status') === 'success'
                && (int) data_get($gatewayData, 'amount') === $visaApplication->total_minor
                && strtoupper((string) data_get($gatewayData, 'currency')) === $visaApplication->currency
                && $reference === $visaApplication->payment_reference;

            if (! $validVisaPayment) {
                $travelLogger->record('visa', 'payment_webhook', 'paystack', [
                    'reference' => $reference,
                    'application' => $visaApplication->reference,
                ], ['verified' => false], [
                    'status' => 'failed',
                    'error_message' => 'Paystack webhook amount, currency or status did not match the visa application.',
                ]);

                return response()->json(['received' => true]);
            }

            $visaApplication->update([
                'status' => 'submitted',
                'paid_at' => now(),
                'gateway_response' => $gatewayData,
            ]);

            try {
                Mail::to(data_get($visaApplication->contact, 'email'))
                    ->send(new VisaApplicationConfirmation($visaApplication->fresh('visa')));
                $visaApplication->update(['confirmation_sent_at' => now()]);
            } catch (Throwable $exception) {
                report($exception);
            }

            $travelLogger->record('visa', 'payment_webhook', 'paystack', [
                'reference' => $reference,
                'application' => $visaApplication->reference,
            ], ['verified' => true], ['status' => 'success']);

            return response()->json(['received' => true]);
        }

        // Paystack retries webhooks. Unknown or already completed references must
        // still return 200 so the gateway does not keep retrying indefinitely.
        if ($attempt->order_id) {
            return response()->json(['received' => true]);
        }

        $valid = data_get($gatewayData, 'status') === 'success'
            && (int) data_get($gatewayData, 'amount') === $attempt->amount_minor
            && strtoupper((string) data_get($gatewayData, 'currency')) === $attempt->currency
            && $reference === $attempt->reference;

        $productType = $attempt->hotel_offer_id ? 'hotel' : 'flight';
        $offerId = $attempt->hotel_offer_id ?: $attempt->travel_offer_id;

        if (! $valid) {
            $travelLogger->record($productType, 'payment_webhook', 'paystack', [
                'reference' => $reference,
            ], ['verified' => false], [
                'status' => 'failed',
                'offer_id' => $offerId,
                'error_message' => "Paystack webhook amount, currency or status did not match the {$productType} payment attempt.",
            ]);

            return response()->json(['received' => true]);
        }

        $attempt->update([
            'status' => 'paid',
            'verified_at' => $attempt->verified_at ?: now(),
            'gateway_response' => $gatewayData,
        ]);
        $travelLogger->record($productType, 'payment_webhook', 'paystack', [
            'reference' => $attempt->reference,
        ], ['verified' => true], [
            'offer_id' => $offerId,
        ]);

        // The browser callback completes the local development booking. Once
        // this URL is publicly reachable, the same paid attempt is safely picked
        // up by the callback without charging or creating a second PNR.
        return response()->json(['received' => true]);
    }
}

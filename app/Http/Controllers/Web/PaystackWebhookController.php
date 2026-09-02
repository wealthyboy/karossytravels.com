<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CheckoutPaymentAttempt;
use App\Support\TravelLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        // Paystack retries webhooks. Unknown or already completed references must
        // still return 200 so the gateway does not keep retrying indefinitely.
        if (! $attempt || $attempt->order_id) {
            return response()->json(['received' => true]);
        }

        $valid = data_get($gatewayData, 'status') === 'success'
            && (int) data_get($gatewayData, 'amount') === $attempt->amount_minor
            && strtoupper((string) data_get($gatewayData, 'currency')) === $attempt->currency
            && $reference === $attempt->reference;

        if (! $valid) {
            $travelLogger->record('flight', 'payment_webhook', 'paystack', [
                'reference' => $reference,
            ], ['verified' => false], [
                'status' => 'failed',
                'offer_id' => $attempt->travel_offer_id,
                'error_message' => 'Paystack webhook amount, currency or status did not match the payment attempt.',
            ]);

            return response()->json(['received' => true]);
        }

        $attempt->update([
            'status' => 'paid',
            'verified_at' => $attempt->verified_at ?: now(),
            'gateway_response' => $gatewayData,
        ]);
        $travelLogger->record('flight', 'payment_webhook', 'paystack', [
            'reference' => $attempt->reference,
        ], ['verified' => true], [
            'offer_id' => $attempt->travel_offer_id,
        ]);

        // The browser callback completes the local development booking. Once
        // this URL is publicly reachable, the same paid attempt is safely picked
        // up by the callback without charging or creating a second PNR.
        return response()->json(['received' => true]);
    }
}

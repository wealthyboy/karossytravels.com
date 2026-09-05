<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CheckoutPaymentAttempt;
use App\Models\HotelOffer;
use App\Models\TravelOffer;
use App\Payments\PaystackService;
use App\Support\PhoneCountryCodes;
use App\Travel\FlightRevalidationService;
use App\Travel\HotelOrderService;
use App\Travel\MobileCheckoutService;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

final class MobileCheckoutController extends Controller
{
    public function flight(Request $request, TravelOffer $offer, FlightRevalidationService $revalidation, ExchangeRateService $rates, PaystackService $paystack): JsonResponse
    {
        $data = $request->validate($this->flightRules());
        try {
            $validation = $revalidation->revalidate($offer);
            if ($validation['price_changed'] ?? false) {
                return response()->json(['message' => 'The fare changed. Return to the offer and review the new total before paying.'], 409);
            }
            $currency = $this->currency($request, $offer->currency);
            $amount = $rates->convertMinor($offer->fresh()->selling_total_minor, $offer->currency, $currency)['amount_minor'];
            $primary = $data['travellers'][0];
            $phone = PhoneCountryCodes::normalize($data['contact']['phone_code'], $data['contact']['phone']);
            return $this->initialize($request, $paystack, $currency, $amount, strtolower($data['contact']['email']), [
                'travel_offer_id' => $offer->id,
                'checkout_payload' => [
                    'travellers' => $data['travellers'],
                    'customer' => [...$primary, 'email' => strtolower($data['contact']['email']), 'phone' => $phone],
                ],
                'booking_type' => 'flight',
            ]);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'This fare could not be prepared for payment. Please retry or choose another flight.'], 422);
        }
    }

    public function hotel(Request $request, HotelOffer $offer, HotelOrderService $orders, ExchangeRateService $rates, PaystackService $paystack): JsonResponse
    {
        $request->merge(['phone' => PhoneCountryCodes::normalize((string) $request->input('phone_code', '+234'), $request->input('phone'))]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190'], 'phone_code' => ['required', 'regex:/^\+[0-9]{1,4}$/'],
            'phone' => ['required', 'regex:/^\+[0-9]{7,15}$/'], 'special_requests' => ['nullable', 'string', 'max:1000'],
            'terms' => ['accepted'],
        ]);
        try {
            $orders->assertCanCreate($offer);
            $currency = $this->currency($request, $offer->currency);
            $amount = $rates->convertMinor($offer->fresh()->selling_total_minor, $offer->currency, $currency)['amount_minor'];
            return $this->initialize($request, $paystack, $currency, $amount, strtolower($data['email']), [
                'hotel_offer_id' => $offer->id,
                'checkout_payload' => ['customer' => $data, 'special_requests' => $data['special_requests'] ?? null],
                'booking_type' => 'hotel',
            ]);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'This room could not be prepared for payment. Please choose another available room.'], 422);
        }
    }

    public function status(Request $request, CheckoutPaymentAttempt $attempt, PaystackService $paystack, MobileCheckoutService $checkout): JsonResponse
    {
        abort_unless(hash_equals((string) $attempt->client_token_hash, hash('sha256', (string) $request->header('X-Checkout-Token'))), 404);
        if ($attempt->order_id) {
            return ApiResponse::success($request, $this->completed($attempt));
        }
        try {
            $verified = $paystack->verify($attempt->reference);
            if (data_get($verified, 'status') !== 'success') {
                return ApiResponse::success($request, ['status' => 'pending', 'reference' => $attempt->reference]);
            }
            $matches = (int) data_get($verified, 'amount') === $attempt->amount_minor
                && strtoupper((string) data_get($verified, 'currency')) === $attempt->currency
                && (string) data_get($verified, 'reference') === $attempt->reference;
            if (! $matches) {
                throw new \RuntimeException('Payment details do not match the booking total.');
            }
            $attempt->update(['status' => 'paid', 'verified_at' => now(), 'gateway_response' => $verified]);
            $claimed = CheckoutPaymentAttempt::whereKey($attempt->id)->whereNull('reservation_attempted_at')->update(['reservation_attempted_at' => now()]);
            if ($claimed === 1) {
                $checkout->complete($attempt->fresh(), $verified);
            }
            $attempt->refresh();
            return ApiResponse::success($request, $attempt->order_id ? $this->completed($attempt) : ['status' => 'processing', 'reference' => $attempt->reference]);
        } catch (Throwable $exception) {
            report($exception);
            return ApiResponse::success($request, ['status' => $attempt->status === 'paid' ? 'processing' : 'pending', 'reference' => $attempt->reference]);
        }
    }

    private function initialize(Request $request, PaystackService $paystack, string $currency, int $amount, string $email, array $context): JsonResponse
    {
        $token = Str::random(64);
        $reference = ($context['booking_type'] === 'hotel' ? 'KAR-MHT-' : 'KAR-MFL-').Str::upper(Str::random(18));
        $attempt = CheckoutPaymentAttempt::create([
            'travel_offer_id' => $context['travel_offer_id'] ?? null,
            'hotel_offer_id' => $context['hotel_offer_id'] ?? null,
            'session_fingerprint' => hash('sha256', $token.'|mobile'),
            'client_token_hash' => hash('sha256', $token),
            'reference' => $reference,
            'currency' => $currency,
            'amount_minor' => $amount,
            'email' => $email,
            'addon_ids' => [],
            'checkout_payload' => $context['checkout_payload'],
        ]);
        $payment = $paystack->initialize($email, $amount, $currency, $reference, [
            'payment_attempt_id' => $attempt->id,
            'booking_type' => $context['booking_type'],
            'client_platform' => 'mobile',
        ]);
        $attempt->update(['access_code' => $payment['access_code'] ?? null]);

        return ApiResponse::success($request, [
            'attempt_id' => $attempt->id,
            'reference' => $reference,
            'client_token' => $token,
            'authorization_url' => $payment['authorization_url'] ?? null,
            'price' => ['currency' => $currency, 'total_minor' => $amount],
            'status' => 'pending',
        ], status: 201);
    }

    private function completed(CheckoutPaymentAttempt $attempt): array
    {
        $order = $attempt->order()->with('bookings')->firstOrFail();
        $booking = $order->bookings->first();
        return [
            'status' => 'completed', 'reference' => $order->reference,
            'order_id' => $order->id, 'booking_type' => $booking?->product_type,
            'provider_locator' => $booking?->provider_locator,
            'price' => ['currency' => $order->currency, 'total_minor' => $order->total_minor],
        ];
    }

    private function currency(Request $request, string $fallback): string
    {
        $currency = strtoupper((string) $request->input('currency', $fallback));
        return in_array($currency, ['NGN', 'USD'], true) ? $currency : 'USD';
    }

    private function flightRules(): array
    {
        return [
            'currency' => ['nullable', Rule::in(['NGN', 'USD'])], 'terms' => ['accepted'],
            'travellers' => ['required', 'array', 'min:1', 'max:9'], 'travellers.*.type' => ['required', Rule::in(['ADT', 'CNN', 'INF'])],
            'travellers.*.title' => ['required', Rule::in(['Mr', 'Mrs', 'Ms', 'Miss', 'Dr'])],
            'travellers.*.first_name' => ['required', 'string', 'max:80'], 'travellers.*.last_name' => ['required', 'string', 'max:80'],
            'travellers.*.date_of_birth' => ['required', 'date', 'before:today'], 'travellers.*.gender' => ['required', Rule::in(['male', 'female', 'unspecified'])],
            'travellers.*.nationality' => ['required', 'size:2'], 'travellers.*.passport_number' => ['required', 'alpha_num', 'min:5', 'max:30'],
            'travellers.*.passport_country' => ['required', 'size:2'], 'travellers.*.passport_expiry' => ['required', 'date', 'after:today'],
            'contact.email' => ['required', 'email:rfc'], 'contact.phone_code' => ['required', 'regex:/^\+[0-9]{1,4}$/'],
            'contact.phone' => ['required', 'string', 'min:7', 'max:24'],
        ];
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CheckoutPaymentAttempt;
use App\Models\Customer;
use App\Models\HotelOffer;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaystackService;
use App\Support\HotelSearchRecovery;
use App\Support\PhoneCountryCodes;
use App\Support\TravelLogger;
use App\Travel\CheckoutCustomerResolver;
use App\Travel\Exceptions\BookingCreationException;
use App\Travel\Exceptions\CheckoutIdentityException;
use App\Travel\HotelOrderService;
use App\Travel\Pricing\DisplayCurrencyResolver;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

final class HotelCheckoutController extends Controller
{
    public function show(Request $request, HotelOffer $offer, DisplayCurrencyResolver $resolver, ExchangeRateService $rates): View|RedirectResponse
    {
        $offer->loadMissing('search');
        if ($offer->expires_at->isPast()) {
            return redirect()->route('hotels.results', HotelSearchRecovery::parameters($offer->search))
                ->with('warning', 'That hotel rate expired, so we are refreshing the latest rooms and prices for you.');
        }

        $currency = $this->checkoutCurrency($request, $resolver);
        $total = $rates->convertMinor($offer->selling_total_minor, $offer->currency, $currency);
        $customer = $request->user()
            ? Customer::query()->where('user_id', $request->user()->id)->first()
            : null;
        $recoverableAttempt = CheckoutPaymentAttempt::query()
            ->where('hotel_offer_id', $offer->id)
            ->where('session_fingerprint', $this->fingerprint($request, $offer))
            ->where('status', 'paid')
            ->whereNull('order_id')
            ->latest()
            ->first();

        return view('hotels.checkout', compact('offer', 'currency', 'total', 'customer', 'recoverableAttempt'));
    }

    public function initialize(
        Request $request,
        HotelOffer $offer,
        DisplayCurrencyResolver $resolver,
        ExchangeRateService $rates,
        HotelOrderService $orders,
        CheckoutCustomerResolver $customerResolver,
        TravelLogger $logger,
    ): JsonResponse {
        $phoneCode = (string) $request->input('phone_code', '+234');
        $request->merge([
            'phone_code' => $phoneCode,
            'phone' => PhoneCountryCodes::normalize($phoneCode, $request->input('phone')),
        ]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone_code' => ['required', 'string', 'regex:/^\+[0-9]{1,4}$/'],
            'phone' => ['required', 'string', 'regex:/^\+[0-9]{7,15}$/'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'terms' => ['accepted'],
        ]);

        $offer->loadMissing('search');
        if ($offer->expires_at->isPast()) {
            return response()->json([
                'message' => 'This hotel rate has expired. No payment has been started. Please search again.',
                'error_code' => 'HOTEL_RATE_EXPIRED',
            ], 410);
        }

        $unresolvedPayment = CheckoutPaymentAttempt::query()
            ->where('hotel_offer_id', $offer->id)
            ->where('session_fingerprint', $this->fingerprint($request, $offer))
            ->where('status', 'paid')
            ->whereNull('order_id')
            ->latest()
            ->first();
        if ($unresolvedPayment) {
            return $this->paidPendingResponse($unresolvedPayment);
        }

        try {
            // Confirm supplier bookability before opening Paystack. Payment is
            // never collected for a rate lacking the BookingKey or guarantee
            // credentials required for a supplier reservation.
            $orders->assertCanCreate($offer);
        } catch (Throwable $exception) {
            report($exception);
            $logger->record('hotel', 'booking_preflight', $offer->provider, ['offer_id' => $offer->id], [], [
                'status' => 'failed',
                'session_id' => $offer->search()->value('session_id'),
                'offer_id' => $offer->id,
                'error_message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'This room cannot be confirmed right now. No payment has been started. Please choose another available room or contact Karossy support.',
                'error_code' => 'HOTEL_RATE_NOT_BOOKABLE',
            ], 422);
        }

        try {
            // Resolve ownership before payment. The signed-in account owns the
            // order, while these guest/contact fields remain booking-specific.
            $customer = $customerResolver->resolve(
                $request->user(),
                ['first_name' => $data['first_name'], 'last_name' => $data['last_name']],
                ['email' => $data['email'], 'phone' => $data['phone']],
            );
        } catch (CheckoutIdentityException $exception) {
            report($exception);
            $logger->record('hotel', 'checkout_identity', 'karossy', [
                'offer_id' => $offer->id,
                'user_id' => $request->user()?->id,
                'contact_email' => strtolower($data['email']),
            ], [], [
                'status' => 'failed',
                'session_id' => $offer->search()->value('session_id'),
                'offer_id' => $offer->id,
                'error_message' => $exception->getMessage(),
            ]);

            $payload = [
                'message' => $exception->publicMessage,
                'error_code' => 'ACCOUNT_PROFILE_ERROR',
            ];
            if ($exception->field) {
                $payload['errors'] = [$exception->field => [$exception->publicMessage]];
            }

            return response()->json($payload, 422);
        }

        $demoPayment = $this->demoEnabled();
        $publicKey = null;
        if (! $demoPayment) {
            $publicKey = trim((string) config('services.paystack.public_key'));
            if ($publicKey === '') {
                return response()->json([
                    'message' => 'Online payment is temporarily unavailable. No payment has been started. Please contact Karossy support.',
                    'error_code' => 'PAYMENT_NOT_CONFIGURED',
                ], 503);
            }
        }

        $currency = $this->checkoutCurrency($request, $resolver);
        $amount = $rates->convertMinor($offer->fresh()->selling_total_minor, $offer->currency, $currency)['amount_minor'];
        $token = (string) $request->session()->get("hotel_checkout.{$offer->id}.token", Str::random(64));
        $request->session()->put("hotel_checkout.{$offer->id}", ['guest' => $data, 'token' => $token]);

        $paymentEmail = strtolower(trim((string) ($request->user()?->email ?: $data['email'])));
        $attempt = CheckoutPaymentAttempt::create([
            'hotel_offer_id' => $offer->id,
            'travel_offer_id' => null,
            'user_id' => $request->user()?->id,
            'customer_id' => $customer->exists ? $customer->id : null,
            'session_fingerprint' => $this->fingerprint($request, $offer),
            'reference' => 'KAR-HTL-'.Str::upper(Str::random(18)),
            'gateway' => $demoPayment ? 'demo' : 'paystack',
            'currency' => $currency,
            'amount_minor' => $amount,
            // Payment identity is separate from the lead guest. Logged-in
            // checkouts charge/receipt the account email; guests use lead email.
            'email' => $paymentEmail,
            'addon_ids' => [],
            'checkout_payload' => ['guest' => $data],
        ]);

        if ($demoPayment) {
            $gatewayData = [
                'status' => 'success',
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $attempt->reference,
                'channel' => 'local_demo',
            ];
            $attempt->update([
                'status' => 'paid',
                'verified_at' => now(),
                'gateway_response' => $gatewayData,
            ]);
            $this->claimReservationAttempt($attempt);

            try {
                $order = $this->finalize($request, $offer, $attempt, $data, $orders, $customerResolver, $logger, $gatewayData, 'demo');
            } catch (BookingCreationException $exception) {
                return $this->paidBookingFailure($offer, $attempt, $exception, $logger);
            } catch (Throwable $exception) {
                return $this->paidInternalFailure($offer, $attempt, $exception, $logger);
            }

            return $this->success($request, $order);
        }

        return response()->json([
            'public_key' => $publicKey,
            'reference' => $attempt->reference,
            'email' => $attempt->email,
            'amount_minor' => $amount,
            'currency' => $currency,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'metadata' => [
                'payment_attempt_id' => $attempt->id,
                'hotel_offer_id' => $offer->id,
                'booking_type' => 'hotel',
            ],
        ]);
    }

    public function verify(
        Request $request,
        HotelOffer $offer,
        PaystackService $paystack,
        HotelOrderService $orders,
        CheckoutCustomerResolver $customerResolver,
        TravelLogger $logger,
    ): JsonResponse {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
        ]);
        $attempt = CheckoutPaymentAttempt::query()
            ->where('hotel_offer_id', $offer->id)
            ->where('reference', $data['reference'])
            ->where('session_fingerprint', $this->fingerprint($request, $offer))
            ->firstOrFail();

        if ($attempt->order_id && ($order = Order::query()->find($attempt->order_id))) {
            $this->reconcileCompletedOrder($attempt, $order, $orders);

            return $this->success($request, $order->fresh());
        }

        $checkout = is_array($attempt->checkout_payload)
            ? $attempt->checkout_payload
            : $request->session()->get("hotel_checkout.{$offer->id}");
        if (! is_array($checkout) || empty($checkout['guest'])) {
            $paid = $attempt->status === 'paid' || $attempt->verified_at !== null;

            return response()->json([
                'message' => "Payment reference {$attempt->reference} cannot be finalized automatically because the checkout details are unavailable. Do not pay again; contact Karossy support with this reference.",
                'payment_confirmed' => $paid,
                'booking_pending' => $paid,
                'payment_locked' => $paid,
                'reference' => $attempt->reference,
                'error_code' => 'CHECKOUT_CONTEXT_MISSING',
            ], $paid ? 202 : 422);
        }

        $verified = is_array($attempt->gateway_response) ? $attempt->gateway_response : [];
        $gateway = (string) ($attempt->gateway ?: 'paystack');

        if (! ($attempt->status === 'paid' || $attempt->verified_at !== null)) {
            try {
                $localCallback = $this->localCallbackFinalizationEnabled();
                $verified = $localCallback
                    ? [
                        'status' => 'success',
                        'amount' => $attempt->amount_minor,
                        'currency' => $attempt->currency,
                        'reference' => $attempt->reference,
                        'channel' => 'paystack_test_callback',
                        'id' => $data['transaction_id'] ?? null,
                        'local_callback' => true,
                    ]
                    : $paystack->verify($attempt->reference);

                if (data_get($verified, 'status') !== 'success') {
                    return response()->json([
                        'message' => 'We are still confirming this payment. Do not start another payment.',
                        'pending' => true,
                        'payment_locked' => true,
                        'reference' => $attempt->reference,
                        'error_code' => 'PAYMENT_PENDING',
                    ], 202);
                }

                $valid = (int) data_get($verified, 'amount') === $attempt->amount_minor
                    && strtoupper((string) data_get($verified, 'currency')) === $attempt->currency
                    && (string) data_get($verified, 'reference') === $attempt->reference;
                if (! $valid) {
                    throw new \RuntimeException('Paystack verification did not match the exact hotel amount, currency and reference.');
                }

                $gateway = $localCallback ? 'paystack_callback_local' : 'paystack';
                $attempt->update([
                    'status' => 'paid',
                    'verified_at' => now(),
                    'gateway_response' => $verified,
                    'gateway' => $gateway,
                    'failure_stage' => null,
                    'failure_message' => null,
                ]);
            } catch (Throwable $exception) {
                report($exception);
                $logger->record('hotel', 'payment_verification', 'paystack', [
                    'offer_id' => $offer->id,
                    'reference' => $attempt->reference,
                ], [], [
                    'status' => 'failed',
                    'session_id' => $offer->search()->value('session_id'),
                    'offer_id' => $offer->id,
                    'error_message' => $exception->getMessage(),
                ]);

                return response()->json([
                    'message' => 'We could not verify the payment yet. Do not pay again while Karossy checks the Paystack reference.',
                    'pending' => true,
                    'payment_locked' => true,
                    'reference' => $attempt->reference,
                    'error_code' => 'PAYMENT_VERIFICATION_PENDING',
                ], 202);
            }
        }

        $attempt->refresh();
        if ($attempt->reservation_attempted_at !== null) {
            return $this->paidPendingResponse($attempt);
        }

        if (! $this->claimReservationAttempt($attempt)) {
            return $this->paidPendingResponse($attempt->fresh());
        }

        try {
            $order = $this->finalize(
                $request,
                $offer,
                $attempt->fresh(),
                (array) $checkout['guest'],
                $orders,
                $customerResolver,
                $logger,
                $verified,
                $gateway,
            );
        } catch (BookingCreationException $exception) {
            return $this->paidBookingFailure($offer, $attempt, $exception, $logger);
        } catch (Throwable $exception) {
            return $this->paidInternalFailure($offer, $attempt, $exception, $logger);
        }

        return $this->success($request, $order);
    }

    public function complete(Request $request, Order $order): View
    {
        $ownsOrder = $request->user() && (int) $order->user_id === (int) $request->user()->id;
        abort_unless($ownsOrder || $request->session()->get("completed_orders.{$order->id}") === true, 404);

        return view('hotels.checkout-complete', [
            'order' => $order->load('bookings.addons'),
            'booking' => $order->bookings->firstOrFail(),
        ]);
    }

    /** @param array<string, mixed> $guest @param array<string, mixed> $gatewayData */
    private function finalize(
        Request $request,
        HotelOffer $offer,
        CheckoutPaymentAttempt $attempt,
        array $guest,
        HotelOrderService $orders,
        CheckoutCustomerResolver $customerResolver,
        TravelLogger $logger,
        array $gatewayData,
        string $gateway,
    ): Order {
        $identity = [
            'first_name' => $guest['first_name'] ?? null,
            'last_name' => $guest['last_name'] ?? null,
        ];
        $contact = [
            'email' => $guest['email'] ?? null,
            'phone' => $guest['phone'] ?? null,
        ];
        $customer = $attempt->customer_id
            ? Customer::query()->find($attempt->customer_id)
            : $customerResolver->transientCustomer($identity, $contact);

        if (! $customer) {
            throw new BookingCreationException(
                'identity',
                'Payment is confirmed, but the account profile linked to this hotel checkout is unavailable. Do not pay again; Karossy support is reviewing it.',
                "Hotel payment attempt {$attempt->id} references missing customer {$attempt->customer_id}.",
            );
        }

        $bookingContact = $customerResolver->bookingContact($identity, $contact);
        $order = $orders->create(
            $offer->fresh(),
            $customer,
            specialRequests: $guest['special_requests'] ?? null,
            sendConfirmation: false,
            bookingContact: $bookingContact,
            ownerUserId: $attempt->user_id,
        );
        $booking = $order->bookings()->firstOrFail();

        // Persist the supplier locator immediately. If anything after this point
        // fails, retries can recover this exact order instead of selling again.
        $attempt->update([
            'order_id' => $order->id,
            'provider_locator' => $booking->provider_locator,
        ]);
        $order->update([
            'currency' => $attempt->currency,
            'subtotal_minor' => $attempt->amount_minor,
            'fees_minor' => 0,
            'total_minor' => $attempt->amount_minor,
        ]);

        $this->ensurePaymentRecord($attempt, $order, $gatewayData, $gateway);
        $attempt->update([
            'status' => 'completed',
            'order_id' => $order->id,
            'provider_locator' => $booking->provider_locator,
            'failure_stage' => null,
            'failure_message' => null,
        ]);
        $request->session()->put("completed_orders.{$order->id}", true);

        // Booking confirmation goes to the booking contact. The payment receipt
        // goes to the signed-in account email, or the booking contact for guests.
        $orders->sendConfirmation($order->fresh());
        $orders->sendReceipt($order->fresh());
        $logger->record('hotel', 'payment', $gateway, [
            'offer_id' => $offer->id,
            'reference' => $attempt->reference,
        ], [
            'order_id' => $order->id,
            'amount_minor' => $attempt->amount_minor,
            'currency' => $attempt->currency,
        ], [
            'session_id' => $offer->search()->value('session_id'),
            'order_id' => $order->id,
        ]);

        return $order;
    }

    private function claimReservationAttempt(CheckoutPaymentAttempt $attempt): bool
    {
        return CheckoutPaymentAttempt::query()
            ->whereKey($attempt->id)
            ->whereNull('reservation_attempted_at')
            ->update(['reservation_attempted_at' => now()]) === 1;
    }

    private function paidBookingFailure(
        HotelOffer $offer,
        CheckoutPaymentAttempt $attempt,
        BookingCreationException $exception,
        TravelLogger $logger,
    ): JsonResponse {
        report($exception);
        $attempt->update([
            'status' => 'paid',
            'failure_stage' => $exception->stage,
            'failure_message' => str($exception->getMessage())->limit(5000)->toString(),
            'provider_locator' => $exception->providerLocator ?: $attempt->provider_locator,
        ]);
        $logger->record('hotel', 'booking_finalization', $offer->provider, [
            'offer_id' => $offer->id,
            'reference' => $attempt->reference,
        ], [], [
            'status' => 'failed',
            'session_id' => $offer->search()->value('session_id'),
            'offer_id' => $offer->id,
            'error_message' => '['.$exception->stage.'] '.$exception->getMessage(),
        ]);

        return $this->paidPendingResponse($attempt->fresh(), $exception->publicMessage);
    }

    private function paidInternalFailure(
        HotelOffer $offer,
        CheckoutPaymentAttempt $attempt,
        Throwable $exception,
        TravelLogger $logger,
    ): JsonResponse {
        report($exception);
        $attempt->update([
            'status' => 'paid',
            'failure_stage' => 'internal',
            'failure_message' => str($exception->getMessage())->limit(5000)->toString(),
        ]);
        $logger->record('hotel', 'booking_finalization', 'karossy', [
            'offer_id' => $offer->id,
            'reference' => $attempt->reference,
        ], [], [
            'status' => 'failed',
            'session_id' => $offer->search()->value('session_id'),
            'offer_id' => $offer->id,
            'error_message' => $exception->getMessage(),
        ]);

        return $this->paidPendingResponse(
            $attempt->fresh(),
            'Payment is confirmed, but Karossy could not finish the local hotel booking workflow. Do not pay again; support is reviewing it.',
        );
    }

    private function paidPendingResponse(CheckoutPaymentAttempt $attempt, ?string $message = null): JsonResponse
    {
        $stage = (string) ($attempt->failure_stage ?: 'processing');
        $defaultMessage = match ($stage) {
            'supplier' => 'Payment is confirmed, but the hotel supplier has not confirmed the reservation. Do not pay again; Karossy is reviewing the supplier response.',
            'availability' => 'Payment is confirmed, but the hotel rate became unavailable before reservation completion. Do not pay again; Karossy is reviewing the booking.',
            'persistence' => $attempt->provider_locator
                ? "Payment is confirmed and the hotel supplier returned locator {$attempt->provider_locator}, but Karossy is reconciling the booking record. Do not pay again."
                : 'Payment is confirmed and the hotel supplier responded, but Karossy is reconciling the booking record. Do not pay again.',
            'identity' => 'Payment is confirmed, but the account link for this hotel booking needs Karossy support review. Do not pay again.',
            'internal' => 'Payment is confirmed, but hotel booking completion needs Karossy support review. Do not pay again.',
            default => 'Payment is confirmed and this hotel reservation has already been sent for confirmation. Do not pay again; Karossy is reviewing the result.',
        };

        return response()->json([
            'message' => ($message ?: $defaultMessage).' Reference: '.$attempt->reference.'.',
            'payment_confirmed' => true,
            'booking_pending' => true,
            'payment_locked' => true,
            'reference' => $attempt->reference,
            'locator' => $attempt->provider_locator,
            'failure_stage' => $stage,
            'error_code' => match ($stage) {
                'supplier' => 'HOTEL_RESERVATION_FAILED',
                'availability' => 'HOTEL_RATE_UNAVAILABLE_AFTER_PAYMENT',
                'persistence' => 'HOTEL_BOOKING_SAVE_FAILED',
                'identity' => 'ACCOUNT_LINK_FAILED_AFTER_PAYMENT',
                'internal' => 'HOTEL_BOOKING_FINALIZATION_FAILED',
                default => 'BOOKING_UNDER_REVIEW',
            },
        ], 202);
    }

    /** @param array<string, mixed> $gatewayData */
    private function ensurePaymentRecord(CheckoutPaymentAttempt $attempt, Order $order, array $gatewayData, string $gateway): void
    {
        Payment::query()->firstOrCreate(
            ['gateway_reference' => $attempt->reference],
            [
                'order_id' => $order->id,
                'gateway' => $gateway,
                'status' => $gateway === 'demo' ? 'simulated' : 'paid',
                'currency' => $attempt->currency,
                'amount_minor' => $attempt->amount_minor,
                'paid_at' => now(),
                'metadata' => [
                    'channel' => data_get($gatewayData, 'channel'),
                    'transaction_id' => data_get($gatewayData, 'id'),
                    'verification_mode' => $gateway === 'paystack_callback_local'
                        ? 'local_callback_unverified'
                        : 'server_verified',
                ],
            ],
        );
    }

    private function reconcileCompletedOrder(CheckoutPaymentAttempt $attempt, Order $order, HotelOrderService $orders): void
    {
        if (! ($attempt->status === 'paid' || $attempt->verified_at !== null || $attempt->status === 'completed')) {
            return;
        }

        $gatewayData = is_array($attempt->gateway_response) ? $attempt->gateway_response : [];
        $gateway = (string) ($attempt->gateway ?: 'paystack');
        $this->ensurePaymentRecord($attempt, $order, $gatewayData, $gateway);
        $wasCompleted = $attempt->status === 'completed';
        $attempt->update([
            'status' => 'completed',
            'failure_stage' => null,
            'failure_message' => null,
        ]);
        if (! $wasCompleted) {
            $orders->sendConfirmation($order->fresh());
            $orders->sendReceipt($order->fresh());
        }
    }

    private function success(Request $request, Order $order): JsonResponse
    {
        return response()->json([
            'message' => 'Your hotel reservation was created.',
            'reference' => $order->reference,
            'redirect' => route('hotels.checkout.complete', $order),
        ], 201);
    }

    private function checkoutCurrency(Request $request, DisplayCurrencyResolver $resolver): string
    {
        $currency = $resolver->resolve($request);

        return in_array($currency, ['NGN', 'USD'], true) ? $currency : 'USD';
    }

    private function fingerprint(Request $request, HotelOffer $offer): string
    {
        return hash(
            'sha256',
            (string) $request->session()->get("hotel_checkout.{$offer->id}.token", '').'|'.$offer->id.'|'.config('app.key'),
        );
    }

    private function demoEnabled(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('travel.checkout.demo_payment_enabled', false);
    }

    private function localCallbackFinalizationEnabled(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('travel.checkout.local_callback_finalization', false)
            && str_starts_with(trim((string) config('services.paystack.public_key')), 'pk_test_')
            && trim((string) config('services.paystack.secret_key')) === '';
    }
}

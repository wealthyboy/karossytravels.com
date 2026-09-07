<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFlightTravellersRequest;
use App\Models\Customer;
use App\Models\Addon;
use App\Models\CheckoutPaymentAttempt;
use App\Models\FairRule;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TravelOffer;
use App\Payments\PaystackService;
use App\Support\TravelLogger;
use App\Travel\AirOrderService;
use App\Travel\CheckoutCustomerResolver;
use App\Travel\Exceptions\BookingCreationException;
use App\Travel\Exceptions\CheckoutIdentityException;
use App\Travel\FlightRevalidationService;
use App\Travel\FlightTicketingService;
use App\Travel\Pricing\DisplayCurrencyResolver;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

final class FlightCheckoutController extends Controller
{
    public function travellers(
        Request $request,
        TravelOffer $offer,
        FlightRevalidationService $revalidation,
        DisplayCurrencyResolver $resolver,
        ExchangeRateService $rates,
    ): View|RedirectResponse {
        if (! $offer->last_validated_at || $offer->last_validated_at->lt(now()->subMinutes(15))) {
            try {
                $revalidation->revalidate($offer);
            } catch (Throwable $exception) {
                report($exception);

                return redirect()->route('flights.review', $offer)->with('error', 'The live fare could not be confirmed. Please retry or choose another fare.');
            }
        }

        $customer = $request->user()
            ? Customer::query()->where('user_id', $request->user()->id)->first()
            : null;
        $airlineCode = strtoupper((string) data_get($offer->fare_summary, 'validating_airline', ''));
        $rules = FairRule::query()->currentlyActive()->forAirline($airlineCode)
            ->orderBy('is_karossey_rule')->orderBy('title')->get();
        $addons = Addon::query()->where('type', 'flight')->where('active', true)->orderBy('title')->get()
            ->map(function (Addon $addon) use ($resolver, $request, $rates): Addon {
                $addon->setAttribute('display_price', $rates->convertMinor($addon->price_cents, $addon->currency, $resolver->resolve($request)));

                return $addon;
            });

        return view('checkout.travellers', [
            ...$this->offerData($request, $offer->fresh(), $resolver, $rates),
            'types' => $this->passengerTypes($offer),
            'customer' => $customer,
            'fareRules' => $rules,
            'addons' => $addons,
            'airlineCode' => $airlineCode,
            'demoPaymentEnabled' => $this->demoPaymentEnabled(),
        ]);
    }

    public function storeTravellers(StoreFlightTravellersRequest $request, TravelOffer $offer): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $expectedTypes = $this->passengerTypes($offer);
        $actualTypes = collect($validated['travellers'])->pluck('type')->values()->all();

        if ($actualTypes !== $expectedTypes) {
            throw ValidationException::withMessages([
                'travellers' => 'The passenger list no longer matches this flight search. Please search again.',
            ]);
        }

        $request->session()->put("flight_checkout.{$offer->id}", [
            'travellers' => $validated['travellers'],
            'contact' => $validated['contact'],
            'notifications' => (bool) ($validated['notifications'] ?? false),
            'token' => $request->session()->get("flight_checkout.{$offer->id}.token", Str::random(64)),
        ]);

        $redirect = route('checkout.payment', $offer);

        return $request->expectsJson()
            ? response()->json(['message' => 'Traveller details saved.', 'redirect' => $redirect])
            : redirect($redirect);
    }

    public function payment(
        Request $request,
        TravelOffer $offer,
        DisplayCurrencyResolver $resolver,
        ExchangeRateService $rates,
    ): View|RedirectResponse {
        return redirect()->route('checkout.travellers', $offer);
    }

    public function confirm(
        Request $request,
        TravelOffer $offer,
        FlightRevalidationService $revalidation,
        DisplayCurrencyResolver $resolver,
        ExchangeRateService $rates,
        AirOrderService $orders,
        FlightTicketingService $ticketing,
        CheckoutCustomerResolver $customerResolver,
        TravelLogger $travelLogger,
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'terms' => ['accepted'],
            'addons' => ['nullable', 'array'],
            'addons.*' => ['uuid', 'distinct', Rule::exists('addons', 'id')->where(fn ($query) => $query->where('type', 'flight')->where('active', true))],
        ]);
        $sessionKey = "flight_checkout.{$offer->id}";
        $checkout = $request->session()->get($sessionKey);

        if (! is_array($checkout) || empty($checkout['travellers']) || empty($checkout['contact'])) {
            return $this->failure($request, 'Your checkout session has expired. Enter the traveller details again.', route('checkout.travellers', $offer), 422, 'CHECKOUT_SESSION_EXPIRED');
        }

        $unresolvedPayment = CheckoutPaymentAttempt::query()
            ->where('travel_offer_id', $offer->id)
            ->where('session_fingerprint', $this->sessionFingerprint($request, $offer))
            ->where('status', 'paid')
            ->whereNull('order_id')
            ->latest()
            ->first();
        if ($unresolvedPayment) {
            return response()->json([
                'message' => 'Payment is already confirmed for this checkout. Do not pay again; Karossy is completing the reservation.',
                'payment_confirmed' => true,
                'booking_pending' => true,
                'payment_locked' => true,
                'reference' => $unresolvedPayment->reference,
                'error_code' => 'PAID_BOOKING_UNRESOLVED',
            ], 409);
        }

        try {
            $validation = $revalidation->revalidate($offer);
        } catch (Throwable $exception) {
            report($exception);
            $travelLogger->record('flight', 'revalidation', $offer->provider, ['offer_id' => $offer->id], [], [
                'status' => 'failed',
                'session_id' => $offer->flightSearch()->value('session_id'),
                'offer_id' => $offer->id,
                'error_message' => $exception->getMessage(),
            ]);

            return $this->failure(
                $request,
                'The live fare could not be confirmed before payment. No payment has been started. Please retry or choose another fare.',
                route('checkout.travellers', $offer),
                422,
                'FARE_REVALIDATION_FAILED',
            );
        }

        if ($validation['price_changed'] ?? false) {
            return $this->failure(
                $request,
                'The airline changed the fare. We refreshed your total; please review it before confirming again.',
                route('checkout.travellers', $offer),
                409,
                'FARE_CHANGED',
            );
        }

        try {
            $customer = $customerResolver->resolve(
                $request->user(),
                (array) data_get($checkout, 'travellers.0', []),
                (array) data_get($checkout, 'contact', []),
            );
        } catch (CheckoutIdentityException $exception) {
            report($exception);
            $travelLogger->record('flight', 'checkout_identity', 'karossy', [
                'offer_id' => $offer->id,
                'user_id' => $request->user()?->id,
                'contact_email' => strtolower((string) data_get($checkout, 'contact.email')),
            ], [], [
                'status' => 'failed',
                'session_id' => $offer->flightSearch()->value('session_id'),
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

        $demoPayment = $this->demoPaymentEnabled();
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

        $addons = Addon::query()->whereIn('id', $validated['addons'] ?? [])->where('type', 'flight')->where('active', true)->get();
        $currency = $resolver->resolve($request);
        if (! in_array($currency, ['NGN', 'USD'], true)) {
            $currency = 'USD';
        }
        $base = $rates->convertMinor($offer->fresh()->selling_total_minor, $offer->currency, $currency)['amount_minor'];
        $addonTotal = $addons->sum(fn (Addon $addon): int => $rates->convertMinor($addon->price_cents, $addon->currency, $currency)['amount_minor']);
        $amount = $base + $addonTotal;
        $reference = 'KAR-PAY-'.Str::upper(Str::random(18));
        $paymentEmail = strtolower(trim((string) ($request->user()?->email ?: data_get($checkout, 'contact.email'))));
        $attempt = CheckoutPaymentAttempt::create([
            'travel_offer_id' => $offer->id,
            'user_id' => $request->user()?->id,
            'customer_id' => $customer->exists ? $customer->id : null,
            'session_fingerprint' => $this->sessionFingerprint($request, $offer),
            'gateway' => $demoPayment ? 'demo' : 'paystack',
            'reference' => $reference,
            'currency' => $currency,
            'amount_minor' => $amount,
            // Paystack and the Karossy payment receipt belong to the payer. For a
            // signed-in checkout that is the account email; guest checkout uses
            // the booking contact email.
            'email' => $paymentEmail,
            'addon_ids' => $addons->pluck('id')->values()->all(),
            'checkout_payload' => $checkout,
        ]);

        if ($demoPayment) {
            $demoResponse = [
                'status' => 'success',
                'amount' => $attempt->amount_minor,
                'currency' => $attempt->currency,
                'reference' => $attempt->reference,
                'channel' => 'local_demo',
                'id' => 'DEMO-'.Str::upper(Str::random(10)),
            ];
            $attempt->update([
                'status' => 'paid',
                'verified_at' => now(),
                'gateway_response' => $demoResponse,
            ]);
            $travelLogger->record('flight', 'payment', 'local_demo', [
                'offer_id' => $offer->id,
                'reference' => $attempt->reference,
                'amount_minor' => $attempt->amount_minor,
                'currency' => $attempt->currency,
            ], ['verified' => true, 'mode' => 'local_demo'], [
                'session_id' => $offer->flightSearch()->value('session_id'),
                'offer_id' => $offer->id,
            ]);

            $this->claimReservationAttempt($attempt);

            try {
                $order = $this->finalizePaidOrder($request, $offer, $attempt, $checkout, $orders, $ticketing, $customerResolver, $rates, $travelLogger, $demoResponse, 'demo');
            } catch (BookingCreationException $exception) {
                return $this->paidBookingFailure($offer, $attempt, $exception, $travelLogger);
            } catch (Throwable $exception) {
                return $this->paidInternalFailure($offer, $attempt, $exception, $travelLogger);
            }

            return $this->success($request, $order);
        }

        $primaryTraveller = (array) data_get($checkout, 'travellers.0', []);
        $metadata = [
            'payment_attempt_id' => $attempt->id,
            'offer_id' => $offer->id,
            'booking_type' => 'flight',
            'traveller_count' => count((array) data_get($checkout, 'travellers', [])),
            'addon_ids' => $attempt->addon_ids ?? [],
            'custom_fields' => [[
                'display_name' => 'Karossy payment reference',
                'variable_name' => 'karossy_reference',
                'value' => $attempt->reference,
            ]],
        ];
        $travelLogger->record('flight', 'payment', 'paystack', [
            'offer_id' => $offer->id,
            'reference' => $attempt->reference,
            'amount_minor' => $attempt->amount_minor,
            'currency' => $attempt->currency,
        ], ['initialized' => true, 'mode' => 'inline_v2'], [
            'session_id' => $offer->flightSearch()->value('session_id'),
            'offer_id' => $offer->id,
        ]);

        return response()->json([
            'message' => 'Payment is ready.',
            'reference' => $attempt->reference,
            'public_key' => $publicKey,
            'email' => $attempt->email,
            'first_name' => (string) data_get($primaryTraveller, 'first_name', ''),
            'last_name' => (string) data_get($primaryTraveller, 'last_name', ''),
            'phone' => (string) data_get($checkout, 'contact.phone', ''),
            'metadata' => $metadata,
            'amount_minor' => $attempt->amount_minor,
            'currency' => $attempt->currency,
        ]);
    }

    public function verifyPayment(
        Request $request,
        TravelOffer $offer,
        PaystackService $paystack,
        AirOrderService $orders,
        FlightTicketingService $ticketing,
        CheckoutCustomerResolver $customerResolver,
        ExchangeRateService $rates,
        TravelLogger $travelLogger,
    ): JsonResponse {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
        ]);
        $attempt = CheckoutPaymentAttempt::query()
            ->where('travel_offer_id', $offer->id)
            ->where('reference', $validated['reference'])
            ->where('session_fingerprint', $this->sessionFingerprint($request, $offer))
            ->firstOrFail();

        if ($attempt->order_id && ($order = Order::query()->find($attempt->order_id))) {
            $this->reconcileCompletedOrder($attempt, $order, $ticketing, $orders);

            return $this->success($request, $order->fresh());
        }

        $checkout = is_array($attempt->checkout_payload)
            ? $attempt->checkout_payload
            : $request->session()->get("flight_checkout.{$offer->id}");
        if (! is_array($checkout) || empty($checkout['travellers']) || empty($checkout['contact'])) {
            return response()->json([
                'message' => "Payment reference {$attempt->reference} cannot be finalized automatically because the checkout details are unavailable. Do not pay again; contact Karossy support with this reference.",
                'payment_confirmed' => $attempt->status === 'paid' || $attempt->verified_at !== null,
                'booking_pending' => $attempt->status === 'paid' || $attempt->verified_at !== null,
                'payment_locked' => $attempt->status === 'paid' || $attempt->verified_at !== null,
                'reference' => $attempt->reference,
                'error_code' => 'CHECKOUT_CONTEXT_MISSING',
            ], ($attempt->status === 'paid' || $attempt->verified_at !== null) ? 202 : 422);
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
                        'id' => $validated['transaction_id'] ?? null,
                        'local_callback' => true,
                    ]
                    : $paystack->verify($attempt->reference);

                if (data_get($verified, 'status') !== 'success') {
                    return response()->json([
                        'message' => 'Waiting for secure payment confirmation. Do not start another payment.',
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
                    throw new \RuntimeException('Paystack verification did not match the exact Karossy booking amount, currency and reference.');
                }

                $gateway = $localCallback ? 'paystack_callback_test' : 'paystack';
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
                $travelLogger->record('flight', 'payment_verification', 'paystack', [
                    'offer_id' => $offer->id,
                    'reference' => $attempt->reference,
                ], [], [
                    'status' => 'failed',
                    'session_id' => $offer->flightSearch()->value('session_id'),
                    'offer_id' => $offer->id,
                    'error_message' => $exception->getMessage(),
                ]);

                return response()->json([
                    'message' => 'We could not verify this payment yet. Do not pay again while Karossy checks the Paystack reference.',
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
            $order = $this->finalizePaidOrder($request, $offer, $attempt->fresh(), $checkout, $orders, $ticketing, $customerResolver, $rates, $travelLogger, $verified, $gateway);
        } catch (BookingCreationException $exception) {
            return $this->paidBookingFailure($offer, $attempt, $exception, $travelLogger);
        } catch (Throwable $exception) {
            return $this->paidInternalFailure($offer, $attempt, $exception, $travelLogger);
        }

        return $this->success($request, $order);
    }

    /** @param array<string, mixed> $checkout @param array<string, mixed> $gatewayData */
    private function finalizePaidOrder(
        Request $request,
        TravelOffer $offer,
        CheckoutPaymentAttempt $attempt,
        array $checkout,
        AirOrderService $orders,
        FlightTicketingService $ticketing,
        CheckoutCustomerResolver $customerResolver,
        ExchangeRateService $rates,
        TravelLogger $travelLogger,
        array $gatewayData,
        string $gateway,
    ): Order {
        $primary = (array) data_get($checkout, 'travellers.0', []);
        $contact = (array) data_get($checkout, 'contact', []);
        $customer = $attempt->customer_id
            ? Customer::query()->find($attempt->customer_id)
            : $customerResolver->transientCustomer($primary, $contact);

        if (! $customer) {
            throw new BookingCreationException(
                'identity',
                'Payment is confirmed, but the account profile linked to this checkout is unavailable. Do not pay again; Karossy support is reviewing it.',
                "Checkout payment attempt {$attempt->id} references missing customer {$attempt->customer_id}.",
            );
        }

        $bookingContact = $customerResolver->bookingContact($primary, $contact);
        $addons = Addon::query()->whereIn('id', $attempt->addon_ids ?? [])->where('type', 'flight')->where('active', true)->get();
        $order = $orders->create(
            $offer->fresh(),
            $customer,
            $checkout['travellers'],
            addons: $addons,
            sendConfirmation: false,
            contact: $bookingContact,
            ownerUserId: $attempt->user_id,
        );

        $booking = $order->bookings()->where('product_type', 'flight')->firstOrFail();
        $attempt->update([
            'order_id' => $order->id,
            'provider_locator' => $booking->provider_locator,
        ]);

        $paidAddonTotal = $addons->sum(fn (Addon $addon): int => $rates->convertMinor($addon->price_cents, $addon->currency, $attempt->currency)['amount_minor']);
        $order->update([
            'currency' => $attempt->currency,
            'subtotal_minor' => max(0, $attempt->amount_minor - $paidAddonTotal),
            'fees_minor' => $paidAddonTotal,
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
        $request->session()->put("flight_checkout.{$offer->id}.order_id", $order->id);
        $request->session()->put("completed_orders.{$order->id}", true);

        // Ticketing is a separate post-booking stage. A ticketing failure does not
        // invalidate a paid reservation/PNR and must never trigger another charge.
        $ticketing->issueAfterPayment($booking);

        // Booking confirmation goes to the booking contact. The payment receipt
        // goes to the signed-in account email, or the booking contact for guests.
        $orders->sendConfirmation($order->fresh());
        $orders->sendReceipt($order->fresh());
        $travelLogger->record('flight', 'payment', match ($gateway) {
            'demo' => 'local_demo',
            'paystack_callback_test' => 'paystack_test_callback',
            default => 'paystack',
        }, [
            'offer_id' => $offer->id,
            'reference' => $attempt->reference,
        ], [
            'verified' => true,
            'order_id' => $order->id,
            'amount_minor' => $attempt->amount_minor,
            'currency' => $attempt->currency,
        ], [
            'session_id' => $offer->flightSearch()->value('session_id'),
            'offer_id' => $offer->id,
            'order_id' => $order->id,
        ]);

        return $order;
    }

    public function complete(Request $request, Order $order): View
    {
        $ownsOrder = $request->user() && (int) $order->user_id === (int) $request->user()->id;
        abort_unless($ownsOrder || $request->session()->get("completed_orders.{$order->id}") === true, 404);

        return view('checkout.complete', [
            'order' => $order->load(['bookings.tickets', 'bookings.addons', 'bookings.travelOffer.flightSearch']),
            'booking' => $order->bookings->firstOrFail(),
        ]);
    }

    private function claimReservationAttempt(CheckoutPaymentAttempt $attempt): bool
    {
        return CheckoutPaymentAttempt::query()
            ->whereKey($attempt->id)
            ->whereNull('reservation_attempted_at')
            ->update(['reservation_attempted_at' => now()]) === 1;
    }

    private function paidBookingFailure(TravelOffer $offer, CheckoutPaymentAttempt $attempt, BookingCreationException $exception, TravelLogger $travelLogger): JsonResponse
    {
        report($exception);
        $attempt->update([
            'status' => 'paid',
            'failure_stage' => $exception->stage,
            'failure_message' => str($exception->getMessage())->limit(5000)->toString(),
            'provider_locator' => $exception->providerLocator ?: $attempt->provider_locator,
        ]);
        $travelLogger->record('flight', 'booking_finalization', $offer->provider, [
            'offer_id' => $offer->id,
            'reference' => $attempt->reference,
        ], [], [
            'status' => 'failed',
            'session_id' => $offer->flightSearch()->value('session_id'),
            'offer_id' => $offer->id,
            'error_message' => '['.$exception->stage.'] '.$exception->getMessage(),
        ]);

        return $this->paidPendingResponse($attempt->fresh(), $exception->publicMessage);
    }

    private function paidInternalFailure(TravelOffer $offer, CheckoutPaymentAttempt $attempt, Throwable $exception, TravelLogger $travelLogger): JsonResponse
    {
        report($exception);
        $attempt->update([
            'status' => 'paid',
            'failure_stage' => 'internal',
            'failure_message' => str($exception->getMessage())->limit(5000)->toString(),
        ]);
        $travelLogger->record('flight', 'booking_finalization', 'karossy', [
            'offer_id' => $offer->id,
            'reference' => $attempt->reference,
        ], [], [
            'status' => 'failed',
            'session_id' => $offer->flightSearch()->value('session_id'),
            'offer_id' => $offer->id,
            'error_message' => $exception->getMessage(),
        ]);

        return $this->paidPendingResponse(
            $attempt->fresh(),
            'Payment is confirmed, but Karossy could not finish the local booking workflow. Do not pay again; support is reviewing it.',
        );
    }

    private function paidPendingResponse(CheckoutPaymentAttempt $attempt, ?string $message = null): JsonResponse
    {
        $stage = (string) ($attempt->failure_stage ?: 'processing');
        $defaultMessage = match ($stage) {
            'supplier' => 'Payment is confirmed, but the airline reservation is not yet confirmed. Do not pay again; Karossy is reviewing the supplier response.',
            'availability' => 'Payment is confirmed, but the fare became unavailable before reservation completion. Do not pay again; Karossy is reviewing the booking.',
            'payload' => 'Payment is confirmed, but Karossy could not prepare the airline reservation request. Do not pay again; support is reviewing the booking details.',
            'persistence' => $attempt->provider_locator
                ? "Payment is confirmed and the airline returned PNR {$attempt->provider_locator}, but Karossy is reconciling the booking record. Do not pay again."
                : 'Payment is confirmed and the airline responded, but Karossy is reconciling the booking record. Do not pay again.',
            'identity' => 'Payment is confirmed, but the account link for this booking needs Karossy support review. Do not pay again.',
            'internal' => 'Payment is confirmed, but booking completion needs Karossy support review. Do not pay again.',
            default => 'Payment is confirmed and this reservation has already been sent for booking. Do not pay again; Karossy is reviewing the result.',
        };

        return response()->json([
            'message' => ($message ?: $defaultMessage).' Reference: '.$attempt->reference.'.',
            'payment_confirmed' => true,
            'booking_pending' => true,
            'payment_locked' => true,
            'reference' => $attempt->reference,
            'pnr' => $attempt->provider_locator,
            'failure_stage' => $stage,
            'error_code' => match ($stage) {
                'supplier' => 'AIRLINE_RESERVATION_FAILED',
                'availability' => 'FARE_UNAVAILABLE_AFTER_PAYMENT',
                'payload' => 'AIRLINE_REQUEST_BUILD_FAILED',
                'persistence' => 'BOOKING_SAVE_FAILED',
                'identity' => 'ACCOUNT_LINK_FAILED_AFTER_PAYMENT',
                'internal' => 'BOOKING_FINALIZATION_FAILED',
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
                'status' => in_array($gateway, ['demo', 'paystack_callback_test'], true) ? 'simulated' : 'paid',
                'currency' => $attempt->currency,
                'amount_minor' => $attempt->amount_minor,
                'paid_at' => now(),
                'metadata' => [
                    'channel' => data_get($gatewayData, 'channel'),
                    'transaction_id' => data_get($gatewayData, 'id'),
                ],
            ],
        );
    }

    private function reconcileCompletedOrder(CheckoutPaymentAttempt $attempt, Order $order, FlightTicketingService $ticketing, AirOrderService $orders): void
    {
        if ($attempt->status === 'paid' || $attempt->verified_at !== null) {
            $gatewayData = is_array($attempt->gateway_response) ? $attempt->gateway_response : [];
            $gateway = (string) ($attempt->gateway ?: 'paystack');
            $this->ensurePaymentRecord($attempt, $order, $gatewayData, $gateway);
            $wasCompleted = $attempt->status === 'completed';
            $attempt->update([
                'status' => 'completed',
                'failure_stage' => null,
                'failure_message' => null,
            ]);
            $booking = $order->bookings()->where('product_type', 'flight')->first();
            if ($booking) {
                $ticketing->issueAfterPayment($booking);
            }
            if (! $wasCompleted) {
                $orders->sendConfirmation($order->fresh());
                $orders->sendReceipt($order->fresh());
            }
        }
    }

    /** @return array<int, string> */
    private function passengerTypes(TravelOffer $offer): array
    {
        $search = $offer->flightSearch()->firstOrFail();

        return [
            ...array_fill(0, $search->adults, 'ADT'),
            ...array_fill(0, $search->children, 'CNN'),
            ...array_fill(0, $search->infants, 'INF'),
        ];
    }

    /** @return array<string, mixed> */
    private function offerData(Request $request, TravelOffer $offer, DisplayCurrencyResolver $resolver, ExchangeRateService $rates): array
    {
        abort_if($offer->expires_at->isPast(), 410, 'This fare has expired. Please search again.');
        $currency = $resolver->resolve($request);
        if (! in_array($currency, ['NGN', 'USD'], true)) {
            $currency = 'USD';
        }

        return [
            'offer' => $offer,
            'currency' => $currency,
            'total' => $rates->convertMinor($offer->selling_total_minor, $offer->currency, $currency),
            'provider' => $rates->convertMinor($offer->provider_total_minor, $offer->currency, $currency),
            'markup' => $rates->convertMinor($offer->markup_minor, $offer->currency, $currency),
        ];
    }

    private function success(Request $request, Order $order): JsonResponse|RedirectResponse
    {
        $order->loadMissing(['bookings.tickets', 'bookings.addons', 'bookings.travelOffer.flightSearch']);
        $booking = $order->bookings->firstOrFail();
        $redirect = route('checkout.complete', $order);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your flight was confirmed by the airline.',
                'reference' => $order->reference,
                'pnr' => $booking->provider_locator,
                'redirect' => $redirect,
                'confirmation_html' => view('checkout._confirmation', compact('order', 'booking'))->render(),
            ], 201);
        }

        return redirect($redirect)->with('success', 'Your flight was confirmed by the airline.');
    }

    private function failure(Request $request, string $message, string $redirect, int $status, ?string $errorCode = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(array_filter([
                'message' => $message,
                'redirect' => $redirect,
                'error_code' => $errorCode,
            ]), $status);
        }

        return redirect($redirect)->with('error', $message);
    }

    private function sessionFingerprint(Request $request, TravelOffer $offer): string
    {
        $checkoutToken = (string) $request->session()->get("flight_checkout.{$offer->id}.token", '');

        return hash('sha256', $checkoutToken.'|'.$offer->id.'|'.config('app.key'));
    }

    private function demoPaymentEnabled(): bool
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

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFlightTravellersRequest;
use App\Models\Addon;
use App\Models\CheckoutPaymentAttempt;
use App\Models\Customer;
use App\Models\FairRule;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TravelOffer;
use App\Payments\PaystackService;
use App\Support\TravelLogger;
use App\Travel\AirOrderService;
use App\Travel\FlightRevalidationService;
use App\Travel\FlightTicketingService;
use App\Travel\Pricing\DisplayCurrencyResolver;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $recoverableAttempt = CheckoutPaymentAttempt::query()
            ->where('travel_offer_id', $offer->id)
            ->where('session_fingerprint', $this->sessionFingerprint($request, $offer))
            ->where('status', 'paid')
            ->latest()
            ->first();

        return view('checkout.travellers', [
            ...$this->offerData($request, $offer->fresh(), $resolver, $rates),
            'types' => $this->passengerTypes($offer),
            'customer' => $customer,
            'fareRules' => $rules,
            'addons' => $addons,
            'airlineCode' => $airlineCode,
            'demoPaymentEnabled' => $this->demoPaymentEnabled(),
            'recoverableAttempt' => $recoverableAttempt,
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
            return $this->failure($request, 'Your checkout session has expired. Enter the traveller details again.', route('checkout.travellers', $offer), 422);
        }

        $paidAttempt = CheckoutPaymentAttempt::query()
            ->where('travel_offer_id', $offer->id)
            ->where('session_fingerprint', $this->sessionFingerprint($request, $offer))
            ->where('status', 'paid')
            ->latest()
            ->first();
        if ($paidAttempt) {
            return response()->json([
                'message' => "Payment is already confirmed. Do not pay again. Karossy is reviewing the airline confirmation for reference {$paidAttempt->reference}.",
                'payment_confirmed' => true,
                'reference' => $paidAttempt->reference,
            ], 409);
        }

        $attempt = null;
        try {
            // Revalidation persists the airline's latest fare on the offer. Build the
            // payment attempt from that fresh value so a price movement updates the
            // checkout total instead of trapping the customer in a retry loop.
            $validation = $revalidation->revalidate($offer);

            $addons = Addon::query()->whereIn('id', $validated['addons'] ?? [])->where('type', 'flight')->where('active', true)->get();
            $currency = $resolver->resolve($request);
            if (! in_array($currency, ['NGN', 'USD'], true)) {
                $currency = 'USD';
            }
            $base = $rates->convertMinor($offer->fresh()->selling_total_minor, $offer->currency, $currency)['amount_minor'];
            $addonTotal = $addons->sum(fn (Addon $addon): int => $rates->convertMinor($addon->price_cents, $addon->currency, $currency)['amount_minor']);
            $amount = $base + $addonTotal;
            $reference = 'KAR-PAY-'.Str::upper(Str::random(18));
            $attempt = CheckoutPaymentAttempt::create([
                'travel_offer_id' => $offer->id,
                'user_id' => $request->user()?->id,
                'session_fingerprint' => $this->sessionFingerprint($request, $offer),
                'reference' => $reference,
                'currency' => $currency,
                'amount_minor' => $amount,
                'email' => strtolower((string) data_get($checkout, 'contact.email')),
                'addon_ids' => $addons->pluck('id')->values()->all(),
            ]);

            // Confirm the airline reservation before opening the payment gateway.
            // A customer must never be charged for an itinerary whose segments
            // have already returned an unconfirmed status such as UC or NN.
            $customer = $this->checkoutCustomer($request, $checkout);
            $order = $orders->create(
                $offer->fresh(),
                $customer,
                $checkout['travellers'],
                addons: $addons,
                sendConfirmation: false,
            );
            $order->update([
                'status' => 'awaiting_payment',
                'currency' => $attempt->currency,
                'subtotal_minor' => $base,
                'fees_minor' => $addonTotal,
                'total_minor' => $attempt->amount_minor,
            ]);
            $attempt->update([
                'order_id' => $order->id,
                'reservation_attempted_at' => now(),
            ]);

            if ($this->demoPaymentEnabled()) {
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

                $order = $this->finalizePaidOrder($request, $offer, $attempt, $ticketing, $travelLogger, $demoResponse, 'demo');

                return $this->success($request, $order);
            }

            $publicKey = trim((string) config('services.paystack.public_key'));
            if ($publicKey === '') {
                throw new \RuntimeException('Online payment is not configured yet. Please contact Karossy support.');
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
        } catch (Throwable $exception) {
            if ($attempt && ! $attempt->order_id) {
                $attempt->update(['status' => 'reservation_failed']);
            }
            report($exception);

            $travelLogger->record('flight', 'payment', $this->demoPaymentEnabled() ? 'local_demo' : 'paystack', [
                'offer_id' => $offer->id,
                'reference' => $attempt?->reference,
            ], [], [
                'status' => 'failed',
                'session_id' => $offer->flightSearch()->value('session_id'),
                'offer_id' => $offer->id,
                'error_message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Payment could not be started right now. Please try again shortly or contact Karossy support.'], 422);
        }

        return response()->json([
            'message' => 'Payment is ready.',
            'price_changed' => (bool) ($validation['price_changed'] ?? false),
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

        if ($attempt->status === 'completed' && $attempt->order_id && ($order = Order::query()->find($attempt->order_id))) {
            // Payment verification can be retried by the browser. Never create a
            // second PNR, but allow the idempotent ticketing stage to recover if
            // Sabre was temporarily unavailable during the first callback.
            $booking = $order->bookings()->where('product_type', 'flight')->first();
            if ($booking) {
                $ticketing->issueAfterPayment($booking);
            }

            return $this->success($request, $order);
        }

        $sessionKey = "flight_checkout.{$offer->id}";
        $checkout = $request->session()->get($sessionKey);
        if (! is_array($checkout) || empty($checkout['travellers']) || empty($checkout['contact'])) {
            return response()->json(['message' => 'Your checkout session expired before payment verification. Please contact Karossy support with your payment reference.'], 422);
        }

        $reservedOrder = $attempt->order_id
            ? Order::query()->with('bookings')->find($attempt->order_id)
            : null;
        $reservedBooking = $reservedOrder?->bookings->firstWhere('product_type', 'flight');
        if (! $reservedOrder || ! $reservedBooking || $reservedBooking->status !== 'confirmed' || blank($reservedBooking->provider_locator)) {
            return response()->json([
                'message' => 'Payment cannot continue because the airline reservation was not confirmed. Please choose another flight.',
            ], 409);
        }

        try {
            $localCallback = $this->localCallbackFinalizationEnabled();
            $webhookVerified = $attempt->status === 'paid' && is_array($attempt->gateway_response);
            $verified = $webhookVerified
                ? $attempt->gateway_response
                : ($localCallback
                ? [
                    'status' => 'success',
                    'amount' => $attempt->amount_minor,
                    'currency' => $attempt->currency,
                    'reference' => $attempt->reference,
                    'channel' => 'paystack_test_callback',
                    'id' => $validated['transaction_id'] ?? null,
                    'local_callback' => true,
                ]
                : $paystack->verify($attempt->reference));
            if (data_get($verified, 'status') !== 'success') {
                return response()->json(['message' => 'Waiting for payment confirmation.', 'pending' => true], 202);
            }
            $valid = (int) data_get($verified, 'amount') === $attempt->amount_minor
                && strtoupper((string) data_get($verified, 'currency')) === $attempt->currency
                && (string) data_get($verified, 'reference') === $attempt->reference;
            if (! $valid) {
                throw new \RuntimeException('Payment has not been verified for the exact booking total.');
            }

            $attempt->update(['status' => 'paid', 'verified_at' => now(), 'gateway_response' => $verified]);
            $gateway = $localCallback ? 'paystack_callback_test' : 'paystack';
        } catch (Throwable $exception) {
            report($exception);

            $travelLogger->record('flight', 'payment', 'paystack', [
                'offer_id' => $offer->id,
                'reference' => $attempt->reference,
            ], [], [
                'status' => 'failed',
                'session_id' => $offer->flightSearch()->value('session_id'),
                'offer_id' => $offer->id,
                'error_message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Payment could not be verified right now. Please try again shortly or contact Karossy support.'], 422);
        }

        $claimed = CheckoutPaymentAttempt::query()
            ->whereKey($attempt->id)
            ->where('status', 'paid')
            ->update(['status' => 'processing']);
        if ($claimed !== 1) {
            $attempt->refresh();
            if ($attempt->status === 'completed' && ($order = Order::query()->find($attempt->order_id))) {
                return $this->success($request, $order);
            }

            return response()->json([
                'message' => "Payment is confirmed. Do not pay again. The booking is being completed for reference {$attempt->reference}.",
                'payment_confirmed' => true,
                'reference' => $attempt->reference,
            ], 409);
        }

        try {
            $order = $this->finalizePaidOrder($request, $offer, $attempt, $ticketing, $travelLogger, $verified, $gateway);
        } catch (Throwable $exception) {
            $attempt->update(['status' => 'paid']);
            report($exception);
            $travelLogger->record('flight', 'booking', $gateway, [
                'offer_id' => $offer->id,
                'reference' => $attempt->reference,
            ], [], [
                'status' => 'failed',
                'session_id' => $offer->flightSearch()->value('session_id'),
                'offer_id' => $offer->id,
                'error_message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => "Your payment is confirmed. Do not pay again. The airline could not immediately confirm the seats, so Karossy is reviewing the booking. Reference: {$attempt->reference}.",
                'payment_confirmed' => true,
                'reference' => $attempt->reference,
            ], 503);
        }

        return $this->success($request, $order);
    }

    /** @param array<string, mixed> $gatewayData */
    private function finalizePaidOrder(
        Request $request,
        TravelOffer $offer,
        CheckoutPaymentAttempt $attempt,
        FlightTicketingService $ticketing,
        TravelLogger $travelLogger,
        array $gatewayData,
        string $gateway,
    ): Order {
        $order = Order::query()->with('bookings')->findOrFail($attempt->order_id);
        $booking = $order->bookings->firstWhere('product_type', 'flight');
        if (! $booking || $booking->status !== 'confirmed' || blank($booking->provider_locator)) {
            throw new \RuntimeException('The confirmed airline reservation could not be recovered after payment.');
        }

        Payment::firstOrCreate([
            'gateway_reference' => $attempt->reference,
        ], [
            'order_id' => $order->id,
            'gateway' => $gateway,
            'status' => in_array($gateway, ['demo', 'paystack_callback_test'], true) ? 'simulated' : 'paid',
            'currency' => $attempt->currency,
            'amount_minor' => $attempt->amount_minor,
            'paid_at' => now(),
            'metadata' => ['channel' => data_get($gatewayData, 'channel'), 'transaction_id' => data_get($gatewayData, 'id')],
        ]);
        $order->update(['status' => 'confirmed']);
        $attempt->update(['status' => 'completed', 'order_id' => $order->id]);
        $request->session()->put("flight_checkout.{$offer->id}.order_id", $order->id);
        $request->session()->put("completed_orders.{$order->id}", true);

        // Payment is complete and the PNR is confirmed. Ticketing is the next
        // supplier stage; failures are persisted for Operations and must never
        // ask the customer to pay a second time.
        $ticketing->issueAfterPayment($booking);

        app(AirOrderService::class)->sendConfirmation($order->fresh());
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

    /** @param array<string, mixed> $checkout */
    private function checkoutCustomer(Request $request, array $checkout): Customer
    {
        $contact = $checkout['contact'];
        $primary = $checkout['travellers'][0];
        $email = strtolower(trim((string) $contact['email']));

        return DB::transaction(function () use ($request, $contact, $primary, $email): Customer {
            $user = $request->user();
            $customer = $user ? Customer::query()->where('user_id', $user->id)->first() : null;

            if (! $customer && $user) {
                $customer = Customer::query()->where('email', $user->email)->first();
            }

            $emailOwner = Customer::query()->where('email', $email)->first();
            if (! $user && $emailOwner?->user_id) {
                throw ValidationException::withMessages(['contact.email' => 'An account already uses this email. Sign in to continue with it.']);
            }
            $customer ??= $emailOwner;

            if (Customer::query()->where('email', $email)->when($customer, fn ($query) => $query->whereKeyNot($customer->id))->exists()) {
                throw ValidationException::withMessages(['contact.email' => 'That contact email belongs to another customer account.']);
            }

            $customer ??= new Customer;
            $customer->discardUnreadablePassportNumber();
            $customer->fill([
                'user_id' => $customer->user_id ?: $user?->id,
                'title' => $primary['title'],
                'first_name' => $primary['first_name'],
                'last_name' => $primary['last_name'],
                'email' => $email,
                'phone' => $contact['phone'],
                'date_of_birth' => $primary['date_of_birth'],
                'gender' => $primary['gender'],
                'nationality' => $primary['nationality'],
                'passport_number' => $primary['passport_number'],
                'passport_country' => $primary['passport_country'],
                'passport_expires_at' => $primary['passport_expiry'],
                'status' => 'active',
            ])->save();

            return $customer;
        });
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

    private function failure(Request $request, string $message, string $redirect, int $status): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'redirect' => $redirect], $status);
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

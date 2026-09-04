<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CheckoutPaymentAttempt;
use App\Models\Customer;
use App\Models\HotelOffer;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaystackService;
use App\Support\TravelLogger;
use App\Support\HotelSearchRecovery;
use App\Support\PhoneCountryCodes;
use App\Travel\HotelOrderService;
use App\Travel\Pricing\DisplayCurrencyResolver;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        $customer = $request->user() ? Customer::where('user_id', $request->user()->id)->first() : null;
        $recoverableAttempt = CheckoutPaymentAttempt::query()
            ->where('hotel_offer_id', $offer->id)
            ->where('session_fingerprint', $this->fingerprint($request, $offer))
            ->where('status', 'paid')
            ->whereNull('order_id')
            ->latest()
            ->first();

        return view('hotels.checkout', compact('offer', 'currency', 'total', 'customer', 'recoverableAttempt'));
    }

    public function initialize(Request $request, HotelOffer $offer, DisplayCurrencyResolver $resolver, ExchangeRateService $rates, HotelOrderService $orders, TravelLogger $logger): JsonResponse
    {
        $phoneCode = (string) $request->input('phone_code', '+234');
        $request->merge(['phone_code' => $phoneCode, 'phone' => PhoneCountryCodes::normalize($phoneCode, $request->input('phone'))]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190'], 'phone_code' => ['required', 'string', 'regex:/^\+[0-9]{1,4}$/'],
            'phone' => ['required', 'string', 'regex:/^\+[0-9]{7,15}$/'],
            'special_requests' => ['nullable', 'string', 'max:1000'], 'terms' => ['accepted'],
        ]);
        $offer->loadMissing('search');
        if ($offer->expires_at->isPast()) return response()->json(['message' => 'This hotel rate has expired. Please search again.'], 410);

        try {
            // Confirm supplier bookability before opening Paystack. Payment is
            // never collected for a rate lacking a usable BookingKey or the
            // agency credentials required by its guarantee rules.
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
                'message' => 'This room cannot be confirmed right now. Please choose another available room or contact Karossy support.',
            ], 422);
        }

        $currency = $this->checkoutCurrency($request, $resolver);
        $amount = $rates->convertMinor($offer->fresh()->selling_total_minor, $offer->currency, $currency)['amount_minor'];
        $token = (string) $request->session()->get("hotel_checkout.{$offer->id}.token", Str::random(64));
        $request->session()->put("hotel_checkout.{$offer->id}", ['guest' => $data, 'token' => $token]);
        $attempt = CheckoutPaymentAttempt::create([
            'hotel_offer_id' => $offer->id, 'travel_offer_id' => null, 'user_id' => $request->user()?->id,
            'session_fingerprint' => $this->fingerprint($request, $offer), 'reference' => 'KAR-HTL-'.Str::upper(Str::random(18)),
            'currency' => $currency, 'amount_minor' => $amount, 'email' => strtolower($data['email']), 'addon_ids' => [],
        ]);

        if ($this->demoEnabled()) {
            $gateway = ['status' => 'success', 'amount' => $amount, 'currency' => $currency, 'reference' => $attempt->reference, 'channel' => 'local_demo'];
            $attempt->update(['status' => 'paid', 'verified_at' => now(), 'gateway_response' => $gateway]);
            return $this->finalize($request, $offer, $attempt, $data, $orders, $logger, $gateway, 'demo');
        }

        $key = trim((string) config('services.paystack.public_key'));
        if ($key === '') return response()->json(['message' => 'Online payment is not configured.'], 422);
        return response()->json([
            'public_key' => $key, 'reference' => $attempt->reference, 'email' => $attempt->email,
            'amount_minor' => $amount, 'currency' => $currency,
            'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'phone' => $data['phone'],
            'metadata' => ['payment_attempt_id' => $attempt->id, 'hotel_offer_id' => $offer->id, 'booking_type' => 'hotel'],
        ]);
    }

    public function verify(Request $request, HotelOffer $offer, PaystackService $paystack, HotelOrderService $orders, TravelLogger $logger): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
        ]);
        $attempt = CheckoutPaymentAttempt::where('hotel_offer_id', $offer->id)->where('reference', $data['reference'])
            ->where('session_fingerprint', $this->fingerprint($request, $offer))->firstOrFail();
        if ($attempt->order_id && ($order = Order::find($attempt->order_id))) return $this->success($request, $order);
        $checkout = $request->session()->get("hotel_checkout.{$offer->id}");
        if (! is_array($checkout) || empty($checkout['guest'])) return response()->json(['message' => 'Your checkout session expired. Contact support with the payment reference.'], 422);

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
                        'id' => $data['transaction_id'] ?? null,
                        'local_callback' => true,
                    ]
                    : $paystack->verify($attempt->reference));
            if (data_get($verified, 'status') !== 'success') return response()->json(['message' => 'Waiting for payment confirmation.', 'pending' => true], 202);
            if ((int) data_get($verified, 'amount') !== $attempt->amount_minor || strtoupper((string) data_get($verified, 'currency')) !== $attempt->currency || (string) data_get($verified, 'reference') !== $attempt->reference) {
                throw new \RuntimeException('Payment does not match the exact hotel reservation total.');
            }
            $attempt->update(['status' => 'paid', 'verified_at' => now(), 'gateway_response' => $verified]);
            $gateway = $localCallback ? 'paystack_callback_local' : 'paystack';
        } catch (Throwable $exception) {
            report($exception);
            $logger->record('hotel', 'payment', 'paystack', ['offer_id' => $offer->id, 'reference' => $attempt->reference], [], [
                'status' => 'failed',
                'session_id' => $offer->search()->value('session_id'),
                'offer_id' => $offer->id,
                'error_message' => $exception->getMessage(),
            ]);
            return response()->json(['message' => 'Payment could not be verified right now. Please try again shortly or contact Karossy support.'], 422);
        }

        try {
            return $this->finalize($request, $offer, $attempt, $checkout['guest'], $orders, $logger, $verified, $gateway);
        } catch (Throwable $exception) {
            report($exception);
            $logger->record('hotel', 'booking', $gateway, ['offer_id' => $offer->id, 'reference' => $attempt->reference], [], [
                'status' => 'failed',
                'session_id' => $offer->search()->value('session_id'),
                'offer_id' => $offer->id,
                'error_message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Your payment was confirmed, but the hotel reservation is still being completed. Please try again shortly or contact Karossy support with your payment reference.',
                'payment_confirmed' => true,
                'reference' => $attempt->reference,
            ], 503);
        }
    }

    public function complete(Request $request, Order $order): View
    {
        abort_unless(($request->user() && (int) $order->user_id === (int) $request->user()->id) || $request->session()->get("completed_orders.{$order->id}") === true, 404);
        return view('hotels.checkout-complete', ['order' => $order->load('bookings.addons'), 'booking' => $order->bookings->firstOrFail()]);
    }

    private function finalize(Request $request, HotelOffer $offer, CheckoutPaymentAttempt $attempt, array $guest, HotelOrderService $orders, TravelLogger $logger, array $gatewayData, string $gateway): JsonResponse
    {
        $order = DB::transaction(function () use ($request, $offer, $attempt, $guest, $orders, $gatewayData, $gateway): Order {
            $customer = $this->customer($request, $guest);
            $order = $orders->create($offer->fresh(), $customer, specialRequests: $guest['special_requests'] ?? null, sendConfirmation: false);
            $order->update(['currency' => $attempt->currency, 'subtotal_minor' => $attempt->amount_minor, 'fees_minor' => 0, 'total_minor' => $attempt->amount_minor]);
            Payment::create(['order_id' => $order->id, 'gateway' => $gateway, 'gateway_reference' => $attempt->reference, 'status' => $gateway === 'demo' ? 'simulated' : 'paid', 'currency' => $attempt->currency, 'amount_minor' => $attempt->amount_minor, 'paid_at' => now(), 'metadata' => ['channel' => data_get($gatewayData, 'channel'), 'transaction_id' => data_get($gatewayData, 'id'), 'verification_mode' => $gateway === 'paystack_callback_local' ? 'local_callback_unverified' : 'server_verified']]);
            $attempt->update(['status' => 'completed', 'order_id' => $order->id]);
            return $order;
        });
        $request->session()->put("completed_orders.{$order->id}", true);
        $orders->sendConfirmation($order->fresh());
        $logger->record('hotel', 'payment', $gateway, ['offer_id' => $offer->id, 'reference' => $attempt->reference], ['order_id' => $order->id, 'amount_minor' => $attempt->amount_minor, 'currency' => $attempt->currency], ['session_id' => $offer->search()->value('session_id'), 'order_id' => $order->id]);
        return $this->success($request, $order);
    }

    private function success(Request $request, Order $order): JsonResponse
    {
        return response()->json(['message' => 'Your hotel reservation was created.', 'reference' => $order->reference, 'redirect' => route('hotels.checkout.complete', $order)], 201);
    }

    private function customer(Request $request, array $guest): Customer
    {
        $email = strtolower(trim($guest['email']));
        $customer = $request->user() ? Customer::where('user_id', $request->user()->id)->first() : null;
        $emailOwner = Customer::where('email', $email)->first();
        if (! $request->user() && $emailOwner?->user_id) throw ValidationException::withMessages(['email' => 'Sign in to use this email address.']);
        $customer ??= $emailOwner ?? new Customer;
        $customer->fill(['user_id' => $customer->user_id ?: $request->user()?->id, 'first_name' => $guest['first_name'], 'last_name' => $guest['last_name'], 'email' => $email, 'phone' => $guest['phone'], 'status' => 'active'])->save();
        return $customer;
    }

    private function checkoutCurrency(Request $request, DisplayCurrencyResolver $resolver): string
    {
        $currency = $resolver->resolve($request);
        return in_array($currency, ['NGN', 'USD'], true) ? $currency : 'USD';
    }

    private function fingerprint(Request $request, HotelOffer $offer): string
    {
        return hash('sha256', (string) $request->session()->get("hotel_checkout.{$offer->id}.token", '').'|'.$offer->id.'|'.config('app.key'));
    }

    private function demoEnabled(): bool
    {
        return app()->environment(['local', 'testing']) && (bool) config('travel.checkout.demo_payment_enabled', false);
    }

    private function localCallbackFinalizationEnabled(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('travel.checkout.local_callback_finalization', false)
            && str_starts_with(trim((string) config('services.paystack.public_key')), 'pk_test_')
            && trim((string) config('services.paystack.secret_key')) === '';
    }
}

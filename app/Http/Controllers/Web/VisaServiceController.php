<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\VisaApplicationConfirmation;
use App\Models\Visa;
use App\Models\VisaApplication;
use App\Payments\PaystackService;
use App\Support\TravelLogger;
use App\Travel\Pricing\DisplayCurrencyResolver;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

final class VisaServiceController extends Controller
{
    public function index(): View
    {
        $active = Visa::query()->where('active', true);

        return view('visas.index', [
            'passportCountries' => (clone $active)->whereNotNull('passport_country')->distinct()->orderBy('passport_country')->pluck('passport_country'),
            'destinations' => (clone $active)->distinct()->orderBy('country')->pluck('country'),
            'featuredVisas' => (clone $active)->orderByDesc('featured')->orderBy('country')->limit(6)->get(),
        ]);
    }

    public function results(Request $request): View
    {
        $criteria = $request->validate([
            'passport_country' => ['required', 'string', 'max:120'],
            'destination' => ['required', 'string', 'max:120'],
            'travellers' => ['required', 'integer', 'min:1', 'max:10'],
        ]);
        $visas = Visa::query()->where('active', true)
            ->where('country', $criteria['destination'])
            ->where(fn ($query) => $query->where('passport_country', $criteria['passport_country'])->orWhere('passport_country', 'Any'))
            ->orderBy('fee_cents')->get();

        return view('visas.results', compact('criteria', 'visas'));
    }

    public function show(Request $request, Visa $visa, DisplayCurrencyResolver $resolver, ExchangeRateService $rates): View
    {
        abort_unless($visa->active, 404);
        $travellers = min(10, max(1, (int) $request->integer('travellers', 1)));

        return view('visas.show', [
            'visa' => $visa,
            'travellers' => $travellers,
            ...$this->pricing($request, $visa, $travellers, $request->boolean('consultation'), $resolver, $rates),
        ]);
    }

    public function checkout(Request $request, Visa $visa, DisplayCurrencyResolver $resolver, ExchangeRateService $rates): View
    {
        abort_unless($visa->active, 404);
        $travellers = min(10, max(1, (int) $request->integer('travellers', 1)));
        $consultation = $request->boolean('consultation');
        $request->session()->put("visa_checkout.{$visa->id}.token", $request->session()->get("visa_checkout.{$visa->id}.token", Str::random(64)));

        return view('visas.checkout', [
            'visa' => $visa,
            'travellers' => $travellers,
            'consultation' => $consultation,
            'demoPaymentEnabled' => $this->demoPaymentEnabled(),
            ...$this->pricing($request, $visa, $travellers, $consultation, $resolver, $rates),
        ]);
    }

    public function initialize(Request $request, Visa $visa, DisplayCurrencyResolver $resolver, ExchangeRateService $rates, TravelLogger $logger): JsonResponse
    {
        abort_unless($visa->active, 404);
        $validated = $request->validate([
            'travellers' => ['required', 'integer', 'min:1', 'max:10'],
            'consultation' => ['nullable', 'boolean'],
            'contact.name' => ['required', 'string', 'max:160'],
            'contact.email' => ['required', 'email:rfc', 'max:190'],
            'contact.phone' => ['required', 'string', 'max:40'],
            'applicants' => ['required', 'array', 'min:1', 'max:10'],
            'applicants.*.first_name' => ['required', 'string', 'max:80'],
            'applicants.*.last_name' => ['required', 'string', 'max:80'],
            'applicants.*.date_of_birth' => ['required', 'date', 'before:today'],
            'applicants.*.passport_number' => ['required', 'string', 'max:40'],
            'applicants.*.passport_expiry' => ['required', 'date', 'after:today'],
            'terms' => ['accepted'],
        ]);
        abort_unless(count($validated['applicants']) === (int) $validated['travellers'], 422, 'Applicant count does not match the selected travellers.');
        $consultation = (bool) ($validated['consultation'] ?? false);
        $pricing = $this->pricing($request, $visa, (int) $validated['travellers'], $consultation, $resolver, $rates);
        $fingerprint = $this->fingerprint($request, $visa);
        $reference = 'KAR-VISA-'.Str::upper(Str::random(16));
        $application = VisaApplication::create([
            'visa_id' => $visa->id,
            'user_id' => $request->user()?->id,
            'reference' => 'KV-'.now()->format('ymd').'-'.Str::upper(Str::random(7)),
            'payment_reference' => $reference,
            'session_fingerprint' => $fingerprint,
            'travellers' => $validated['travellers'],
            'consultation_added' => $consultation,
            'currency' => $pricing['currency'],
            'visa_total_minor' => $pricing['visaTotal']['amount_minor'],
            'consultation_total_minor' => $pricing['consultationTotal']['amount_minor'],
            'total_minor' => $pricing['grandTotal']['amount_minor'],
            'applicants' => $validated['applicants'],
            'contact' => $validated['contact'],
        ]);
        $request->session()->put("visa_checkout.{$visa->id}.application_id", $application->id);
        $logger->record('visa', 'payment', 'paystack', ['application' => $application->reference], ['initialized' => true], ['session_id' => $request->session()->getId()]);

        if ($this->demoPaymentEnabled()) {
            return $this->completeApplication($request, $application, ['status' => 'success', 'reference' => $reference, 'channel' => 'local_demo'], $logger);
        }

        $publicKey = trim((string) config('services.paystack.public_key'));
        if ($publicKey === '') {
            return response()->json(['message' => 'Online payment is not configured yet. Please contact Karossy support.'], 422);
        }

        return response()->json([
            'message' => 'Payment is ready.', 'public_key' => $publicKey, 'reference' => $reference,
            'email' => data_get($validated, 'contact.email'), 'amount_minor' => $application->total_minor,
            'currency' => $application->currency, 'first_name' => data_get($validated, 'applicants.0.first_name'),
            'last_name' => data_get($validated, 'applicants.0.last_name'), 'phone' => data_get($validated, 'contact.phone'),
            'metadata' => ['booking_type' => 'visa', 'visa_application_id' => $application->id, 'application_reference' => $application->reference],
        ]);
    }

    public function verify(Request $request, VisaApplication $application, PaystackService $paystack, TravelLogger $logger): JsonResponse
    {
        $validated = $request->validate(['reference' => ['required', 'string', 'max:100'], 'transaction_id' => ['nullable', 'string', 'max:100']]);
        abort_unless(hash_equals($application->session_fingerprint, $this->fingerprint($request, $application->visa)), 404);
        abort_unless(hash_equals((string) $application->payment_reference, $validated['reference']), 422, 'Payment reference does not match this application.');
        if ($application->paid_at) {
            return $this->completeApplication($request, $application, $application->gateway_response ?? [], $logger);
        }

        try {
            $verified = $this->localCallbackFinalizationEnabled()
                ? ['status' => 'success', 'amount' => $application->total_minor, 'currency' => $application->currency, 'reference' => $application->payment_reference, 'channel' => 'paystack_test_callback', 'id' => $validated['transaction_id'] ?? null]
                : $paystack->verify($application->payment_reference);
            $valid = data_get($verified, 'status') === 'success'
                && (int) data_get($verified, 'amount') === $application->total_minor
                && strtoupper((string) data_get($verified, 'currency')) === $application->currency
                && (string) data_get($verified, 'reference') === $application->payment_reference;
            if (! $valid) {
                throw new \RuntimeException('Payment has not been verified for the exact visa application total.');
            }

            return $this->completeApplication($request, $application, $verified, $logger);
        } catch (Throwable $exception) {
            report($exception);
            $logger->record('visa', 'payment', 'paystack', ['application' => $application->reference], [], ['status' => 'failed', 'error_message' => $exception->getMessage()]);

            return response()->json(['message' => 'Payment could not be verified right now. Please try again shortly or contact Karossy support.'], 422);
        }
    }

    public function complete(Request $request, VisaApplication $application): View
    {
        $owns = $request->user() && (int) $request->user()->id === (int) $application->user_id;
        abort_unless($owns || $request->session()->get("completed_visa_applications.{$application->id}") === true, 404);

        return view('visas.complete', ['application' => $application->load('visa')]);
    }

    /** @return array<string, mixed> */
    private function pricing(Request $request, Visa $visa, int $travellers, bool $consultation, DisplayCurrencyResolver $resolver, ExchangeRateService $rates): array
    {
        $currency = $resolver->resolve($request);
        if (! in_array($currency, ['NGN', 'USD'], true)) {
            $currency = 'USD';
        }
        $visaTotal = $rates->convertMinor($visa->fee_cents * $travellers, $visa->currency ?: 'NGN', $currency);
        $consultationTotal = $rates->convertMinor($consultation ? $visa->consultation_fee_cents : 0, $visa->currency ?: 'NGN', $currency);

        return ['currency' => $currency, 'visaTotal' => $visaTotal, 'consultationTotal' => $consultationTotal, 'grandTotal' => ['amount_minor' => $visaTotal['amount_minor'] + $consultationTotal['amount_minor']]];
    }

    private function completeApplication(Request $request, VisaApplication $application, array $gateway, TravelLogger $logger): JsonResponse
    {
        if (! $application->paid_at) {
            $application->update(['status' => 'submitted', 'paid_at' => now(), 'gateway_response' => $gateway]);
            try {
                Mail::to(data_get($application->contact, 'email'))->send(new VisaApplicationConfirmation($application->fresh('visa')));
                $application->update(['confirmation_sent_at' => now()]);
            } catch (Throwable $exception) {
                report($exception);
            }
            $logger->record('visa', 'payment', 'paystack', ['application' => $application->reference], ['verified' => true], ['session_id' => $request->session()->getId()]);
        }
        $request->session()->put("completed_visa_applications.{$application->id}", true);

        return response()->json(['message' => 'Visa application submitted.', 'reference' => $application->reference, 'redirect' => route('visas.complete', $application)], 201);
    }

    private function fingerprint(Request $request, Visa $visa): string
    {
        return hash('sha256', (string) $request->session()->get("visa_checkout.{$visa->id}.token", '').'|'.$visa->id.'|'.config('app.key'));
    }

    private function demoPaymentEnabled(): bool
    {
        return app()->environment(['local', 'testing']) && (bool) config('travel.checkout.demo_payment_enabled', false);
    }

    private function localCallbackFinalizationEnabled(): bool
    {
        return app()->environment(['local', 'testing']) && (bool) config('travel.checkout.local_callback_finalization', false)
            && str_starts_with(trim((string) config('services.paystack.public_key')), 'pk_test_')
            && trim((string) config('services.paystack.secret_key')) === '';
    }
}

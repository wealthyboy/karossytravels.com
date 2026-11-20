<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateFlightOrderRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Booking;
use App\Models\CrmActivity;
use App\Models\CrmTask;
use App\Models\Customer;
use App\Models\Order;
use App\Models\TravelOffer;
use App\Travel\FlightRevalidationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class FlightQuoteController extends Controller
{
    public function revalidate(Request $request, TravelOffer $offer, FlightRevalidationService $service): JsonResponse
    {
        try {
            $service->revalidate($offer);

            return ApiResponse::success($request, [
                'continue_url' => route('crm.travel.flights.quote.create', $offer),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The live fare could not be confirmed. Please retry or choose another fare.',
                'meta' => ['request_id' => $request->attributes->get('request_id')],
            ], 422);
        }
    }

    public function create(TravelOffer $offer): View
    {
        abort_unless($offer->last_validated_at && $offer->expires_at->isFuture(), 409, 'Revalidate this fare before preparing an invoice.');
        $offer->load('flightSearch');

        $types = [
            ...array_fill(0, $offer->flightSearch->adults, 'ADT'),
            ...array_fill(0, $offer->flightSearch->children, 'CNN'),
            ...array_fill(0, $offer->flightSearch->infants, 'INF'),
        ];

        return view('crm.travel.flight-quote', [
            'offer' => $offer,
            'types' => $types,
            'customers' => Customer::query()->where('status', 'active')->orderBy('first_name')->orderBy('last_name')->get(),
        ]);
    }

    public function store(CreateFlightOrderRequest $request, TravelOffer $offer): RedirectResponse
    {
        abort_unless($offer->last_validated_at && $offer->expires_at->isFuture(), 409, 'This fare must be revalidated again before preparing an invoice.');
        $data = $request->validated();
        $customer = Customer::query()->where('status', 'active')->findOrFail($data['customer_id']);
        $dueAt = now()->addHours(24);

        $booking = DB::transaction(function () use ($offer, $customer, $data, $dueAt, $request): Booking {
            $order = Order::create([
                'reference' => 'KAR-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'user_id' => $request->user()->id,
                'customer_id' => $customer->id,
                'channel' => 'crm',
                'status' => 'pending_payment',
                'payment_mode' => 'invoice',
                'payment_due_at' => $dueAt,
                'currency' => $offer->currency,
                'subtotal_minor' => $offer->selling_total_minor,
                'total_minor' => $offer->selling_total_minor,
                'customer' => [
                    'name' => $customer->full_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ],
                'expires_at' => $dueAt,
            ]);

            $booking = $order->bookings()->create([
                'travel_offer_id' => $offer->id,
                'product_type' => 'flight',
                'provider' => $offer->provider,
                'status' => 'pending',
                'travellers' => $data['travellers'],
                'details' => [
                    'itinerary' => $offer->itinerary,
                    'crm_invoice' => true,
                    'supplier_booking_started' => false,
                    'quote_expires_at' => $dueAt->toIso8601String(),
                ],
            ]);

            $order->payments()->create([
                'gateway' => 'invoice',
                'status' => 'pending',
                'currency' => $offer->currency,
                'amount_minor' => $order->total_minor,
            ]);

            return $booking;
        });

        $booking->refresh();
        CrmActivity::create([
            'customer_id' => $customer->id,
            'booking_id' => $booking->id,
            'actor_user_id' => $request->user()->id,
            'assigned_user_id' => $booking->assigned_user_id,
            'type' => 'note',
            'subject' => 'Pending flight invoice prepared',
            'body' => 'A pending invoice was created. No supplier PNR or ticket has been requested.',
            'metadata' => ['order_reference' => $booking->order->reference],
            'occurred_at' => now(),
        ]);
        CrmTask::create([
            'customer_id' => $customer->id,
            'booking_id' => $booking->id,
            'created_by_user_id' => $request->user()->id,
            'assigned_user_id' => $booking->assigned_user_id,
            'title' => 'Follow up payment for '.$booking->order->reference,
            'description' => 'Confirm payment before supplier booking is started. No PNR exists yet.',
            'priority' => 'high',
            'due_at' => now()->addMinutes(30),
            'metadata' => ['kind' => 'payment_follow_up'],
        ]);

        return redirect()->route('crm.bookings.show', $booking)
            ->with('success', 'Pending invoice prepared and assigned. No supplier PNR or ticket has been created.');
    }
}

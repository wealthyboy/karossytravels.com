<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateFlightOrderRequest;
use App\Models\Customer;
use App\Models\Addon;
use App\Models\Order;
use App\Models\TravelOffer;
use App\Travel\AirOrderService;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class FlightOrderController extends Controller
{
    public function create(Request $request, TravelOffer $offer, ExchangeRateService $rates): View
    {
        abort_unless($offer->last_validated_at && $offer->expires_at->isFuture(), 409, 'Revalidate this fare before booking.');
        $customers = Customer::query()->where('status', 'active')
            ->when($request->user()->isB2b(), fn ($query) => $query->where('owner_user_id', $request->user()->id))
            ->orderBy('first_name')->orderBy('last_name')->get();
        $search = $offer->flightSearch;
        $types = [...array_fill(0, $search->adults, 'ADT'), ...array_fill(0, $search->children, 'CNN'), ...array_fill(0, $search->infants, 'INF')];
        $addons = Addon::query()->where('type', 'flight')->where('active', true)->orderBy('title')->get()
            ->each(function (Addon $addon) use ($offer, $rates): void {
                $addon->setAttribute('display_price_minor', $rates->convertMinor($addon->price_cents, $addon->currency, $offer->currency)['amount_minor']);
            });

        return view('admin.flights.book', compact('offer', 'customers', 'types', 'addons'));
    }

    public function store(CreateFlightOrderRequest $request, TravelOffer $offer, AirOrderService $service): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $customer = Customer::findOrFail($validated['customer_id']);
        abort_if($request->user()->isB2b() && $customer->owner_user_id !== $request->user()->id, 403);

        try {
            $addons = Addon::query()->whereKey($validated['addons'] ?? [])->get();
            $order = $service->create(
                $offer,
                $customer,
                $validated['travellers'],
                $validated['agency_number'] ?? null,
                $addons,
                ['type' => $validated['operator_markup_type'], 'value' => $validated['operator_markup_value'] ?? null],
            );
        } catch (Throwable $exception) {
            report($exception);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'The airline booking could not be completed. Check the API logs and try again.'], 422);
            }
            return back()->withInput()->with('error', 'The airline booking could not be completed. Check the API logs and try again.');
        }

        $redirectUrl = route('admin.flights.orders.show', $order);

        if ($request->expectsJson()) {
            return response()->json([
                'message'   => 'The flight booking was confirmed.',
                'redirect'  => $redirectUrl,
                'reference' => $order->reference,
            ], 201);
        }

        return redirect($redirectUrl)->with('success', 'The flight booking was confirmed.');
    }

    public function show(Order $order): View
    {
        return view('admin.flights.order', ['order' => $order->load('bookings')]);
    }
}

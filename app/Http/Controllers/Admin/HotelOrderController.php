<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateHotelOrderRequest;
use App\Models\Addon;
use App\Models\Customer;
use App\Models\HotelOffer;
use App\Travel\HotelOrderService;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class HotelOrderController extends Controller
{
    public function create(Request $request, HotelOffer $offer, ExchangeRateService $rates): View
    {
        abort_if($offer->expires_at->isPast(), 409, 'This hotel rate has expired. Search again.');
        $offer->load('search');
        $customers = Customer::query()->where('status', 'active')
            ->when($request->user()->isB2b(), fn ($query) => $query->where('owner_user_id', $request->user()->id))
            ->orderBy('first_name')->orderBy('last_name')->get();
        $addons = Addon::query()->where('type', 'hotel')->where('active', true)->orderBy('title')->get()
            ->each(function (Addon $addon) use ($offer, $rates): void {
                $addon->setAttribute('display_price_minor', $rates->convertMinor($addon->price_cents, $addon->currency, $offer->currency)['amount_minor']);
            });

        return view('admin.hotels.book', compact('offer', 'customers', 'addons'));
    }

    public function store(CreateHotelOrderRequest $request, HotelOffer $offer, HotelOrderService $service): RedirectResponse
    {
        $validated = $request->validated();
        $customer = Customer::findOrFail($validated['customer_id']);
        abort_if($request->user()->isB2b() && $customer->owner_user_id !== $request->user()->id, 403);

        try {
            $order = $service->create(
                $offer,
                $customer,
                Addon::query()->whereKey($validated['addons'] ?? [])->get(),
                ['type' => $validated['operator_markup_type'], 'value' => $validated['operator_markup_value'] ?? null],
                $validated['special_requests'] ?? null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', $this->bookingFailureMessage($exception));
        }

        return redirect()->route('admin.bookings.show', $order->bookings->first())
            ->with('success', 'Hotel booking confirmed with the supplier.');
    }

    private function bookingFailureMessage(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'payment-card guarantee')) {
            return 'This rate requires a supplier payment-card guarantee. Choose another pay-later or agency-guaranteed rate.';
        }

        if (str_contains($message, 'agency iata')) {
            return 'This GDS hotel rate requires the Karossy agency IATA number to be configured before it can be confirmed.';
        }

        if (str_contains($message, 'prepayment')) {
            return 'This rate requires supplier prepayment. Choose another pay-later or agency-guaranteed rate.';
        }

        if (str_contains($message, 'rate') && str_contains($message, 'changed')) {
            return 'The supplier changed this hotel rate. Return to the hotel results and select the latest rate before booking.';
        }

        return 'The hotel supplier did not confirm the booking. Check the hotel API logs for the exact supplier response and try again.';
    }
}

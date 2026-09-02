<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use App\Travel\BookingLifecycleService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class BookingController extends Controller
{
    private const PRODUCTS = ['flight', 'hotel', 'visa'];

    private const STATUSES = ['pending', 'confirmed', 'failed', 'cancelled', 'refunded'];

    private const TICKET_STATUSES = ['issued', 'pending', 'unticketed', 'refunded'];

    public function index(Request $request, string $product = 'all'): View
    {
        abort_unless($product === 'all' || in_array($product, self::PRODUCTS, true), 404);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
            'source' => ['nullable', 'string', 'max:50'],
            'provider' => ['nullable', 'string', 'max:50'],
            'ticket_status' => ['nullable', 'in:'.implode(',', self::TICKET_STATUSES)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'in:created_at,booked_at,status,provider,provider_locator,source'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $visible = $this->visibleBookings();
        $productCounts = [
            'all' => (clone $visible)->count(),
            ...collect(self::PRODUCTS)->mapWithKeys(fn (string $type): array => [
                $type => (clone $visible)->where('product_type', $type)->count(),
            ])->all(),
        ];

        $productQuery = clone $visible;
        if ($product !== 'all') {
            $productQuery->where('product_type', $product);
        }

        $summary = [
            'total' => (clone $productQuery)->count(),
            'confirmed' => (clone $productQuery)->where('status', 'confirmed')->count(),
            'pending' => (clone $productQuery)->where('status', 'pending')->count(),
            'cancelled' => (clone $productQuery)->where('status', 'cancelled')->count(),
            'ticketed' => (clone $productQuery)->whereHas('tickets', fn (Builder $query) => $query
                ->where('status', 'issued')->orWhereNotNull('issued_at'))->count(),
        ];

        $sources = (clone $productQuery)->whereNotNull('source')->where('source', '!=', '')
            ->distinct()->orderBy('source')->pluck('source');
        $providers = (clone $productQuery)->whereNotNull('provider')->where('provider', '!=', '')
            ->distinct()->orderBy('provider')->pluck('provider');

        $search = trim((string) ($validated['q'] ?? ''));
        $query = clone $productQuery;
        $query->with(['order.customerProfile', 'tickets'])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('provider_locator', 'like', "%{$search}%")
                    ->orWhere('provider', 'like', "%{$search}%")
                    ->orWhereHas('order', fn (Builder $query) => $query->where('reference', 'like', "%{$search}%"))
                    ->orWhereHas('order.customerProfile', fn (Builder $query) => $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"));
            }))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($validated['provider'] ?? null, fn (Builder $query, string $provider) => $query->where('provider', $provider))
            ->when($validated['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date));

        match ($validated['ticket_status'] ?? null) {
            'issued' => $query->whereHas('tickets', fn (Builder $tickets) => $tickets->where('status', 'issued')->orWhereNotNull('issued_at')),
            'pending' => $query->whereHas('tickets', fn (Builder $tickets) => $tickets->where('status', 'pending')),
            'refunded' => $query->whereHas('tickets', fn (Builder $tickets) => $tickets->where('status', 'refunded')->orWhereNotNull('refunded_at')),
            'unticketed' => $query->whereDoesntHave('tickets', fn (Builder $tickets) => $tickets->where('status', 'issued')->orWhereNotNull('issued_at')),
            default => null,
        };

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $bookings = $query->orderBy($sort, $direction)->paginate(20)->withQueryString();

        return view('admin.bookings.index', compact(
            'bookings', 'product', 'productCounts', 'summary', 'sources', 'providers'
        ));
    }

    public function flights(Request $request): View
    {
        return $this->index($request, 'flight');
    }

    public function hotels(Request $request): View
    {
        return $this->index($request, 'hotel');
    }

    public function visas(Request $request): View
    {
        return $this->index($request, 'visa');
    }

    public function show(Booking $booking): View
    {
        $this->authorizeVisible($booking);

        return view('admin.bookings.show', [
            'booking' => $booking->load(['order.customerProfile', 'order.payments', 'travelOffer', 'tickets', 'addons', 'actions.user']),
        ]);
    }

    public function modify(Request $request, Booking $booking, BookingLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorizeVisible($booking);

        $validated = $request->validate([
            'change_type' => ['required', 'in:dates,route,traveller,contact,cabin,other'],
            'requested_change' => ['required', 'string', 'min:5', 'max:3000'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'internal_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        try {
            $lifecycle->requestModification($booking, $validated);

            return back()->with('success', 'Modification request recorded and the customer was notified.');
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->with('error', 'The modification request could not be completed. Please review the booking and try again.');
        }
    }

    public function voidTickets(Request $request, Booking $booking, BookingLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorizeVisible($booking);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'internal_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        try {
            $action = $lifecycle->void($booking, $validated['reason'], $validated['internal_notes'] ?? null);

            return back()->with('success', $action->status === 'completed'
                ? 'Ticket void completed and the customer was notified.'
                : 'Ticket void request recorded for manual handling; the customer was notified.');
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->with('error', 'The ticket void request could not be completed. Please review the booking and try again.');
        }
    }

    public function cancel(Request $request, Booking $booking, BookingLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorizeVisible($booking);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'internal_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        if ($booking->status === 'cancelled') {
            return back()->with('error', 'Booking already cancelled.');
        }

        try {
            $action = $lifecycle->cancel($booking, $validated['reason'], $validated['internal_notes'] ?? null);

            return back()->with('success', $action->status === 'completed'
                ? 'Booking cancelled and the customer was notified.'
                : 'Cancellation request recorded for manual handling; the customer was notified.');
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->with('error', 'The cancellation request could not be completed. Please review the booking and try again.');
        }
    }

    private function visibleBookings(): Builder
    {
        $query = Booking::query();
        $user = request()->user();

        return $user?->isB2b()
            ? $query->whereHas('order', fn (Builder $order) => $order->where('user_id', $user->id))
            : $query;
    }

    private function authorizeVisible(Booking $booking): void
    {
        abort_unless($this->visibleBookings()->whereKey($booking->getKey())->exists(), 404);
    }
}

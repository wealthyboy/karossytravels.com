<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\FlightSearch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $currency = config('travel.default_currency', 'NGN');
        $user = request()->user();
        $orders = Order::query()->when($user?->isB2b(), fn ($query) => $query->where('user_id', $user->id));
        $bookings = Booking::query()->when($user?->isB2b(), fn ($query) => $query->whereHas('order', fn ($query) => $query->where('user_id', $user->id)));
        $tickets = Ticket::query()->when($user?->isB2b(), fn ($query) => $query->whereHas('booking.order', fn ($query) => $query->where('user_id', $user->id)));
        $payments = Payment::query()->when($user?->isB2b(), fn ($query) => $query->whereHas('order', fn ($query) => $query->where('user_id', $user->id)));
        $paidPayments = (clone $payments)->where('status', 'paid');
        $bookingsCount = (clone $bookings)->count();
        $issuedTickets = (clone $tickets)->where('status', 'issued')->count();
        $currentRevenue = (int) (clone $paidPayments)->where('paid_at', '>=', now()->subDays(30))->sum('amount_minor');
        $previousRevenue = (int) (clone $paidPayments)->whereBetween('paid_at', [now()->subDays(60), now()->subDays(30)])->sum('amount_minor');
        $revenueGrowth = $previousRevenue > 0 ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100 : 0;
        $revenueByDate = (clone $paidPayments)->where('paid_at', '>=', now()->subDays(13)->startOfDay())->get()
            ->groupBy(fn (Payment $payment): string => $payment->paid_at->toDateString())
            ->map(fn (Collection $payments): int => (int) $payments->sum('amount_minor'));
        $maxDailyRevenue = max(1, (int) $revenueByDate->max());
        $confirmedBookings = (clone $bookings)->where('status', 'confirmed')->count();
        $averageBookingSeconds = (clone $bookings)->whereNotNull('booked_at')->get()
            ->avg(fn (Booking $booking): int => $booking->created_at->diffInSeconds($booking->booked_at));

        return view('admin.dashboard', [
            'totalBookings' => $bookingsCount,
            'financialMetrics' => [
                ['label' => 'Flight bookings', 'value' => number_format((clone $bookings)->where('product_type', 'flight')->count()), 'change' => 'All flight booking statuses', 'icon' => 'bi-airplane'],
                ['label' => 'Awaiting ticketing', 'value' => number_format((clone $tickets)->where('status', 'pending')->count()), 'change' => 'Tickets requiring issuance', 'icon' => 'bi-hourglass-split'],
                ['label' => 'Tickets issued', 'value' => number_format($issuedTickets), 'change' => 'Successfully fulfilled', 'icon' => 'bi-ticket-detailed'],
                ['label' => 'Ticketing failed', 'value' => number_format((clone $tickets)->where('status', 'failed')->count()), 'change' => 'Requires immediate attention', 'icon' => 'bi-exclamation-octagon'],
                ['label' => 'Hotel bookings', 'value' => number_format((clone $bookings)->where('product_type', 'hotel')->count()), 'change' => 'All hotel booking statuses', 'icon' => 'bi-building'],
                ['label' => 'Hotels pending', 'value' => number_format((clone $bookings)->where('product_type', 'hotel')->where('status', 'pending')->count()), 'change' => 'Awaiting confirmation', 'icon' => 'bi-calendar2-check'],
                ['label' => 'Cancelled bookings', 'value' => number_format((clone $bookings)->where('status', 'cancelled')->count()), 'change' => 'Flights and hotels', 'icon' => 'bi-calendar2-x'],
                ['label' => 'Refund requests', 'value' => number_format((clone $payments)->whereIn('status', ['refund_requested', 'refund_pending'])->count()), 'change' => 'Waiting to be processed', 'icon' => 'bi-arrow-counterclockwise'],
            ],
            'bookingStatuses' => [
                'Confirmed' => $confirmedBookings,
                'Pending' => (clone $bookings)->where('status', 'pending')->count(),
                'Failed' => (clone $bookings)->where('status', 'failed')->count(),
                'Cancelled' => (clone $bookings)->where('status', 'cancelled')->count(),
                'Refunded' => (clone $bookings)->where('status', 'refunded')->count(),
                'Ticketed' => (clone $bookings)->whereHas('tickets', fn ($query) => $query->where('status', 'issued'))->count(),
                'Unticketed' => (clone $bookings)->where('status', 'confirmed')->whereDoesntHave('tickets', fn ($query) => $query->where('status', 'issued'))->count(),
            ],
            'operationalQueues' => [
                ['label' => 'Tickets awaiting issuance', 'value' => (clone $tickets)->where('status', 'pending')->count(), 'severity' => 'warning', 'icon' => 'bi-ticket-perforated'],
                ['label' => 'Failed ticket issuance', 'value' => (clone $tickets)->where('status', 'failed')->count(), 'severity' => 'danger', 'icon' => 'bi-x-octagon'],
                ['label' => 'Pending hotel confirmations', 'value' => (clone $bookings)->where('product_type', 'hotel')->where('status', 'pending')->count(), 'severity' => 'warning', 'icon' => 'bi-building-exclamation'],
                ['label' => 'Bookings requiring manual review', 'value' => 0, 'severity' => 'danger', 'icon' => 'bi-person-exclamation'],
                ['label' => 'Possible duplicate bookings', 'value' => 0, 'severity' => 'warning', 'icon' => 'bi-copy'],
                ['label' => 'Customers waiting for support', 'value' => 0, 'severity' => 'info', 'icon' => 'bi-headset'],
                ['label' => 'Schedule change notifications', 'value' => 0, 'severity' => 'info', 'icon' => 'bi-calendar2-event'],
            ],
            'revenueTrend' => collect(range(13, 0))->map(fn (int $daysAgo): array => [
                'label' => ($date = now()->subDays($daysAgo))->format('M j'),
                'value' => $value = (int) $revenueByDate->get($date->toDateString(), 0),
                'percentage' => (int) round($value / $maxDailyRevenue * 100),
            ]),
            'customerMetrics' => [
                'returning_customers' => (clone $orders)->whereNotNull('customer_id')->select('customer_id')->groupBy('customer_id')->havingRaw('COUNT(*) > 1')->get()->count(),
                'cancellations' => (clone $bookings)->where('status', 'cancelled')->count(),
                'refunds' => (clone $payments)->where('status', 'refunded')->count(),
                'average_booking_time' => $averageBookingSeconds === null ? '—' : now()->subSeconds((int) $averageBookingSeconds)->diffForHumans(now(), true),
                'revenue_growth' => number_format($revenueGrowth, 1).'%',
            ],
        ]);
    }

    private function money(int $minor, string $currency): string
    {
        $symbol = $currency === 'NGN' ? '₦' : $currency.' ';

        return $symbol.number_format($minor / 100, 2);
    }
}

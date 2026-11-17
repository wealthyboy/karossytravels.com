<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\ServiceEnquiry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = today();
        $bookings = Booking::query();
        $payments = Payment::query()->where('status', 'paid');
        $todayRevenue = (int) (clone $payments)->whereDate('paid_at', $today)->sum('amount_minor');
        $weekBookings = (clone $bookings)->where('created_at', '>=', now()->subDays(6)->startOfDay());
        $weekTotal = (clone $weekBookings)->count();
        $weekConfirmed = (clone $weekBookings)->where('status', 'confirmed')->count();
        $revenueByDay = (clone $payments)->where('paid_at', '>=', now()->subDays(6)->startOfDay())->get()
            ->groupBy(fn (Payment $payment): string => $payment->paid_at->toDateString())
            ->map(fn (Collection $items): int => (int) $items->sum('amount_minor'));
        $revenueMax = max(1, (int) $revenueByDay->max());

        return view('crm.dashboard', [
            'todayBookings' => (clone $bookings)->whereDate('created_at', $today)->count(),
            'todayRevenue' => $todayRevenue,
            'conversionRate' => $weekTotal ? round(($weekConfirmed / $weekTotal) * 100, 1) : 0,
            'openLeads' => ServiceEnquiry::query()->whereNotIn('status', ['closed', 'resolved'])->count(),
            'attention' => [
                ['label' => 'Pending bookings', 'value' => (clone $bookings)->where('status', 'pending')->count(), 'icon' => 'bi-hourglass-split', 'tone' => 'amber', 'url' => route('crm.bookings.index')],
                ['label' => 'Failed bookings', 'value' => (clone $bookings)->where('status', 'failed')->count(), 'icon' => 'bi-exclamation-octagon', 'tone' => 'rose', 'url' => route('crm.bookings.index')],
                ['label' => 'New enquiries', 'value' => ServiceEnquiry::query()->whereIn('status', ['new', 'open', 'pending'])->count(), 'icon' => 'bi-chat-square-text', 'tone' => 'blue', 'url' => route('crm.workspace', 'activities')],
                ['label' => 'Customers', 'value' => Customer::query()->count(), 'icon' => 'bi-people', 'tone' => 'mint', 'url' => route('crm.customers.index')],
            ],
            'recentBookings' => Booking::query()->with('order.customerProfile')->latest()->limit(6)->get(),
            'revenueTrend' => collect(range(6, 0))->map(fn (int $daysAgo): array => [
                'label' => ($date = now()->subDays($daysAgo))->format('D'),
                'value' => (int) $revenueByDay->get($date->toDateString(), 0),
                'height' => max(8, (int) round(((int) $revenueByDay->get($date->toDateString(), 0) / $revenueMax) * 100)),
            ]),
        ]);
    }
}

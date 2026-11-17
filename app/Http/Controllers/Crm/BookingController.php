<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:new,pending,confirmed,completed,cancelled,failed,refunded'],
        ]);
        $query = Booking::query()->with('order.customerProfile');
        $search = trim((string) ($filters['q'] ?? ''));

        $bookings = $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('provider_locator', 'like', "%{$search}%")
                    ->orWhere('provider', 'like', "%{$search}%")
                    ->orWhereHas('order', fn (Builder $order) => $order->where('reference', 'like', "%{$search}%"))
                    ->orWhereHas('order.customerProfile', fn (Builder $customer) => $customer
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('crm.bookings.index', [
            'bookings' => $bookings,
            'summary' => [
                'all' => Booking::query()->count(),
                'pending' => Booking::query()->where('status', 'pending')->count(),
                'confirmed' => Booking::query()->where('status', 'confirmed')->count(),
                'cancelled' => Booking::query()->where('status', 'cancelled')->count(),
            ],
        ]);
    }
}

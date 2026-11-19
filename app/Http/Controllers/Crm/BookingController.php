<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CrmActivity;
use App\Models\User;
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
        $query = Booking::query()->with(['order.customerProfile', 'assignedAgent']);
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

    public function create(): View
    {
        return view('crm.bookings.create');
    }

    public function show(Booking $booking): View
    {
        return view('crm.bookings.show', [
            'booking' => $booking->load(['order.customerProfile', 'assignedAgent', 'activities.actor', 'tasks.assignee']),
            'agents' => $this->agents(),
        ]);
    }

    public function assign(Request $request, Booking $booking)
    {
        $agentIds = $this->agents()->pluck('id');
        $validated = $request->validate(['assigned_user_id' => ['required', 'integer', 'in:'.$agentIds->implode(',')]]);
        $booking->update(['assigned_user_id' => $validated['assigned_user_id'], 'assigned_at' => now()]);
        CrmActivity::create([
            'customer_id' => $booking->order?->customer_id,
            'booking_id' => $booking->id,
            'actor_user_id' => $request->user()->id,
            'assigned_user_id' => $validated['assigned_user_id'],
            'type' => 'note',
            'subject' => 'Booking reassigned',
            'body' => 'A customer agent was assigned to this booking.',
            'occurred_at' => now(),
        ]);

        return back()->with('success', 'Booking ownership updated.');
    }

    private function agents()
    {
        return User::query()
            ->where(fn (Builder $query) => $query->whereNull('status')->orWhere('status', 'active'))
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['customer-agent', 'operations', 'b2b-agent', 'super-admin']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}

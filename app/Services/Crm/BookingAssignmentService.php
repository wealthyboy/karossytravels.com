<?php

namespace App\Services\Crm;

use App\Models\Booking;
use App\Models\User;

final class BookingAssignmentService
{
    public function assignAutomatically(Booking $booking): void
    {
        if ($booking->assigned_user_id) {
            return;
        }

        $agent = User::query()
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', 'active'))
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['customer-agent', 'operations', 'b2b-agent', 'super-admin']))
            ->withCount(['assignedBookings as open_booking_count' => fn ($query) => $query->whereNotIn('status', ['completed', 'cancelled', 'refunded'])])
            ->orderBy('open_booking_count')
            ->orderBy('id')
            ->first();

        if ($agent) {
            $booking->forceFill(['assigned_user_id' => $agent->id, 'assigned_at' => now()])->saveQuietly();
        }
    }
}

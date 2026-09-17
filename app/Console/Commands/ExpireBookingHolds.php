<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

final class ExpireBookingHolds extends Command
{
    protected $signature = 'bookings:expire-holds';
    protected $description = 'Cancel unpaid flight bookings whose hold deadline has passed';

    public function handle(): int
    {
        $orders = Order::query()->where('payment_mode', 'hold')->where('status', 'pending_payment')->whereNotNull('payment_due_at')->where('payment_due_at', '<=', now())->with('bookings')->get();
        foreach ($orders as $order) {
            $order->update(['status' => 'cancelled']);
            $order->bookings->each(fn ($booking) => $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]));
        }
        $this->info("Expired {$orders->count()} hold booking(s).");
        return self::SUCCESS;
    }
}

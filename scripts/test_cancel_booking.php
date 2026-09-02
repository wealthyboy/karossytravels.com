<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Booking;
use App\Http\Controllers\Admin\BookingController;

$id = $argv[1] ?? null;
if (!$id) {
    echo "Usage: php scripts/test_cancel_booking.php {booking_id}\n";
    exit(1);
}

$booking = Booking::find($id);
if (!$booking) {
    echo "Booking not found: $id\n";
    exit(1);
}

echo "Before:\n";
echo "id: {$booking->id}\nstatus: {$booking->status}\ncancelled_at: {$booking->cancelled_at}\n";

$controller = new BookingController();
// Call cancel method (it type-hints Booking)
$response = $controller->cancel($booking);

$booking->refresh();

echo "After:\n";
echo "id: {$booking->id}\nstatus: {$booking->status}\ncancelled_at: {$booking->cancelled_at}\n";

if ($booking->status === 'cancelled') {
    echo "Cancel succeeded.\n";
} else {
    echo "Cancel did not set status to cancelled.\n";
}

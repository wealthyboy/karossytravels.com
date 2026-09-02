<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Booking;

$limit = $argv[1] ?? 20;
$bookings = Booking::orderBy('id','asc')->limit($limit)->get();
if ($bookings->isEmpty()) {
    echo "No bookings found.\n";
    exit(0);
}

echo "DB: " . \DB::connection()->getDatabaseName() . "\n";
echo "Total bookings: " . Booking::count() . "\n\n";
foreach ($bookings as $b) {
    echo "#{$b->id} | order_id={$b->order_id} | status={$b->status} | provider_locator={$b->provider_locator} | created_at={$b->created_at}\n";
}

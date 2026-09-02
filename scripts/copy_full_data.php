<?php
// scripts/copy_full_data.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
\DB::purge('sqlite');
$src = \DB::connection('sqlite');
$dst = \DB::connection();

echo "Building customer map...\n";
$customerMap = [];
$customers = $src->table('customers')->get();
foreach ($customers as $c) {
    $c = (array) $c;
    $existing = $dst->table('customers')->where('email', $c['email'])->first();
    if ($existing) {
        $customerMap[$c['id']] = $existing->id;
        continue;
    }
    try {
        $dst->table('customers')->insert($c);
        $customerMap[$c['id']] = $c['id'];
    } catch (Throwable $e) {
        echo "Failed to insert customer {$c['id']}: {$e->getMessage()}\n";
    }
}

echo "Customers mapped: " . count($customerMap) . "\n";

// Optionally copy travel_offers
echo "Copying travel_offers (best-effort)...\n";
$offerMap = [];
if ($src->getSchemaBuilder()->hasTable('travel_offers')) {
    $offers = $src->table('travel_offers')->get();
    foreach ($offers as $o) {
        $o = (array) $o;
        $exists = $dst->table('travel_offers')->where('id', $o['id'])->first();
        if ($exists) { $offerMap[$o['id']] = $o['id']; continue; }
        try { $dst->table('travel_offers')->insert($o); $offerMap[$o['id']] = $o['id']; }
        catch (Throwable $e) { echo "Skipping travel_offer {$o['id']}: {$e->getMessage()}\n"; }
    }
}
echo "Offers mapped: " . count($offerMap) . "\n";

// Copy orders with mapped customer ids
echo "Copying orders...\n";
$orders = $src->table('orders')->orderBy('created_at')->get();
$insertedOrders = 0;
$orderInsertedIds = [];
foreach ($orders as $o) {
    $row = (array) $o;
    $oldCustomer = $row['customer_id'] ?? null;
    $row['customer_id'] = $oldCustomer && isset($customerMap[$oldCustomer]) ? $customerMap[$oldCustomer] : null;
    // remove id if already exists
    $exists = $dst->table('orders')->where('id', $row['id'])->exists();
    if ($exists) { $orderInsertedIds[] = $row['id']; continue; }
    try {
        $dst->table('orders')->insert($row);
        $insertedOrders++; $orderInsertedIds[] = $row['id'];
    } catch (Throwable $e) {
        echo "Failed to insert order {$row['id']}: {$e->getMessage()}\n";
    }
}
echo "Inserted orders: {$insertedOrders}\n";

// Copy bookings with order mapping and offer mapping
echo "Copying bookings...\n";
$bookings = $src->table('bookings')->get();
$insertedBookings = 0;
foreach ($bookings as $b) {
    $row = (array) $b;
    // ensure order exists
    if (! in_array($row['order_id'], $orderInsertedIds) && ! $dst->table('orders')->where('id', $row['order_id'])->exists()) {
        echo "Order missing for booking {$row['id']}, nulling order_id\n";
        $row['order_id'] = null; // break FK, but keep booking
    }
    // travel_offer
    if (! empty($row['travel_offer_id']) && ! isset($offerMap[$row['travel_offer_id']]) && ! $dst->table('travel_offers')->where('id', $row['travel_offer_id'])->exists()) {
        echo "Travel offer missing for booking {$row['id']}, nulling travel_offer_id\n";
        $row['travel_offer_id'] = null;
    }
    $exists = $dst->table('bookings')->where('id', $row['id'])->exists();
    if ($exists) continue;
    try { $dst->table('bookings')->insert($row); $insertedBookings++; }
    catch (Throwable $e) { echo "Failed to insert booking {$row['id']}: {$e->getMessage()}\n"; }
}

echo "Inserted bookings: {$insertedBookings}\n";

echo "Done.\n";

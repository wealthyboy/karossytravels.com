<?php
// scripts/copy_customers.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
\DB::purge('sqlite');
$src = \DB::connection('sqlite');
$dst = \DB::connection();

$rows = $src->table('customers')->get();
echo "Found " . count($rows) . " customers in SQLite\n";
$inserted = 0;
foreach ($rows as $row) {
    $arr = (array) $row;
    $exists = $dst->table('customers')->where('id', $arr['id'])->exists();
    if ($exists) continue;

    try {
        $dst->table('customers')->insert($arr);
        $inserted++;
    } catch (Throwable $e) {
        echo "Failed to insert customer {$arr['id']}: " . $e->getMessage() . "\n";
    }
}

echo "Inserted {$inserted} customers into MySQL\n";

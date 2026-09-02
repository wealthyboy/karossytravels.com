<?php
// scripts/copy_users.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// ensure sqlite uses the project file
config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
\DB::purge('sqlite');

$src = \DB::connection('sqlite');
$dst = \DB::connection(); // default (mysql)

$rows = $src->table('users')->get();
echo "Found " . count($rows) . " users in SQLite\n";
$inserted = 0;
foreach ($rows as $row) {
    $arr = (array) $row;
    $exists = $dst->table('users')->where('id', $arr['id'])->exists();
    if ($exists) continue;

    // Clean up timestamps if missing
    if (empty($arr['created_at'])) $arr['created_at'] = now();
    if (empty($arr['updated_at'])) $arr['updated_at'] = now();

    try {
        $dst->table('users')->insert($arr);
        $inserted++;
    } catch (Throwable $e) {
        echo "Failed to insert user {$arr['id']}: " . $e->getMessage() . "\n";
    }
}

echo "Inserted {$inserted} users into MySQL\n";

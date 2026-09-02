<?php
// scripts/copy_sqlite_to_mysql.php

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/** @var \Illuminate\Database\Connection $src */
$sqlitePath = database_path('database.sqlite');
if (! file_exists($sqlitePath)) {
    echo "SQLite file not found: {$sqlitePath}\n";
    exit(1);
}
// ensure sqlite connection uses the project database file
config(['database.connections.sqlite.database' => $sqlitePath]);
\DB::purge('sqlite');
$src = \DB::connection('sqlite');
/** @var \Illuminate\Database\Connection $dst */
$dst = \DB::connection(); // default (mysql)

$tables = ['orders', 'bookings'];
foreach ($tables as $table) {
    echo "Processing table: {$table}\n";
    $rows = $src->table($table)->orderBy('created_at')->get();
    echo "Found " . count($rows) . " rows in sqlite.\n";

    $inserted = 0;
    foreach ($rows as $row) {
        $rowArr = (array) $row;

        // skip if already present in destination
        $exists = $dst->table($table)->where('id', $rowArr['id'])->exists();
        if ($exists) {
            continue;
        }

        // ensure JSON columns are proper arrays/strings
        foreach ($rowArr as $k => $v) {
            if ($v !== null && is_string($v)) {
                // try decode json to see if it's JSON text
                $decoded = json_decode($v, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $rowArr[$k] = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                }
            }
        }

        // insert preserving id and timestamps
        try {
            $dst->table($table)->insert($rowArr);
            $inserted++;
        } catch (Throwable $e) {
            echo "Failed to insert row {$rowArr['id']} into {$table}: " . $e->getMessage() . "\n";
        }
    }

    echo "Inserted {$inserted} new rows into {$table}.\n\n";
}

echo "Done.\n";

<?php
// scripts/assign_super_admin.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::first();
if (! $user) {
    echo "No users found.\n";
    exit(1);
}
$role = \App\Models\Role::where('name', 'super-admin')->first();
if (! $role) {
    echo "Role super-admin not found.\n";
    exit(1);
}
$user->roles()->syncWithoutDetaching([$role->id]);
echo "Assigned role super-admin to user {$user->email}\n";

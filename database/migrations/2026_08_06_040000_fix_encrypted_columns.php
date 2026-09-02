<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert JSON columns that are used to store encrypted strings into LONGTEXT
        // so Laravel's encrypted casts can persist binary/base64 ciphertext.
        try {
            DB::statement("ALTER TABLE `orders` MODIFY `customer` LONGTEXT NULL");
        } catch (\Throwable $e) {
            // ignore if alteration not supported or already applied
        }

        try {
            DB::statement("ALTER TABLE `bookings` MODIFY `travellers` LONGTEXT NULL");
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE `orders` MODIFY `customer` JSON NULL");
        } catch (\Throwable $e) {
        }

        try {
            DB::statement("ALTER TABLE `bookings` MODIFY `travellers` JSON NULL");
        } catch (\Throwable $e) {
        }
    }
};

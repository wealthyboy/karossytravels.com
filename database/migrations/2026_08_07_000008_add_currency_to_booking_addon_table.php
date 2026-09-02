<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_addon', function (Blueprint $table): void {
            $table->char('currency', 3)->default('USD')->after('price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('booking_addon', fn (Blueprint $table) => $table->dropColumn('currency'));
    }
};

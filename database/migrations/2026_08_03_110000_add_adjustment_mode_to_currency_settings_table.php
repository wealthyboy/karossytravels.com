<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currency_settings', function (Blueprint $table): void {
            $table->string('adjustment_mode')->default('percentage')->after('adjustment_type');
        });
    }

    public function down(): void
    {
        Schema::table('currency_settings', function (Blueprint $table): void {
            $table->dropColumn('adjustment_mode');
        });
    }
};

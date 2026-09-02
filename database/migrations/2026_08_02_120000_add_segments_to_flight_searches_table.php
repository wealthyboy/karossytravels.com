<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flight_searches', function (Blueprint $table): void {
            $table->json('segments')->nullable()->after('return_date');
        });
    }

    public function down(): void
    {
        Schema::table('flight_searches', function (Blueprint $table): void {
            $table->dropColumn('segments');
        });
    }
};

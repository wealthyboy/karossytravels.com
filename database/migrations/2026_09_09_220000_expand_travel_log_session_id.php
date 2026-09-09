<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_logs', function (Blueprint $table): void {
            // Visa application sessions are Laravel session identifiers, not
            // UUIDs. Keep the column indexable while allowing either format.
            $table->string('session_id', 191)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('travel_logs', function (Blueprint $table): void {
            $table->uuid('session_id')->nullable()->change();
        });
    }
};

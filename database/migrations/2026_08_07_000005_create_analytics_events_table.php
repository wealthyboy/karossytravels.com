<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('analytics_events')) {
            Schema::create('analytics_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('event_type')->index();
                $table->json('payload')->nullable();
                $table->uuid('user_id')->nullable()->index();
                $table->string('ip_address')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // This compatibility migration intentionally leaves the pre-existing
        // analytics_events table intact when it did not create it.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event', 80)->index();
            $table->string('service', 30)->nullable()->index();
            $table->string('funnel_step', 60)->nullable()->index();
            $table->uuid('visitor_id')->nullable()->index();
            $table->uuid('session_id')->index();
            $table->string('source', 40)->nullable()->index();
            $table->json('properties')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['service', 'event', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};

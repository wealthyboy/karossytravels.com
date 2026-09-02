<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('product_type', 24)->index();
            $table->string('stage', 40)->index();
            $table->string('provider', 40)->nullable()->index();
            $table->string('status', 20)->default('success')->index();
            $table->uuid('session_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('offer_id')->nullable()->index();
            $table->uuid('order_id')->nullable()->index();
            $table->string('request_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['product_type', 'stage', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_logs');
    }
};

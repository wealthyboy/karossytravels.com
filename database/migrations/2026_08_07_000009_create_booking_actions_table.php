<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30)->index();
            $table->string('status', 30)->default('requested')->index();
            $table->string('change_type', 40)->nullable();
            $table->text('reason');
            $table->text('requested_change')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('provider_summary')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('customer_notified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_actions');
    }
};

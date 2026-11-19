<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('assigned_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_user_id');
            $table->index(['assigned_user_id', 'status']);
        });

        Schema::create('call_center_extensions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('extension', 20)->unique();
            $table->string('status', 20)->default('offline')->index();
            $table->boolean('can_receive_calls')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('call_center_queues', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('department', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('ring_timeout_seconds')->default(20);
            $table->timestamps();
        });

        Schema::create('call_center_queue_user', function (Blueprint $table): void {
            $table->uuid('call_center_queue_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(1);
            $table->boolean('is_active')->default(true);
            $table->primary(['call_center_queue_id', 'user_id']);
            $table->foreign('call_center_queue_id')->references('id')->on('call_center_queues')->cascadeOnDelete();
        });

        Schema::create('call_center_calls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('call_center_queue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 12)->index();
            $table->string('status', 20)->default('completed')->index();
            $table->string('phone_number', 50)->nullable()->index();
            $table->string('caller_name')->nullable();
            $table->string('outcome', 80)->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('provider_call_id')->nullable()->unique();
            $table->string('recording_url')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_center_calls');
        Schema::dropIfExists('call_center_queue_user');
        Schema::dropIfExists('call_center_queues');
        Schema::dropIfExists('call_center_extensions');
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn('assigned_at');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('payment_due_at')->nullable()->index();
            $table->string('payment_mode', 30)->default('online')->index();
        });

        Schema::create('booking_hold_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('timeout_hours')->default(24);
            $table->string('bank_name')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('sort_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_hold_settings');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_due_at', 'payment_mode']);
        });
    }
};

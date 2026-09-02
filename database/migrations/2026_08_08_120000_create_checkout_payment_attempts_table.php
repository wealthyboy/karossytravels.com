<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_payment_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('travel_offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_fingerprint', 64)->index();
            $table->string('gateway', 40)->default('paystack')->index();
            $table->string('reference')->unique();
            $table->string('access_code')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('email');
            $table->json('addon_ids')->nullable();
            // Encrypted casts produce ciphertext, so this cannot be a native JSON column.
            $table->longText('gateway_response')->nullable();
            $table->foreignUuid('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_payment_attempts');
    }
};

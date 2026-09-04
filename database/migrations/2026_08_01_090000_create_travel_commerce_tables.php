<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flight_searches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('session_id')->index();
            $table->string('channel', 24)->default('consumer')->index();
            $table->string('provider', 40)->index();
            $table->char('origin', 3);
            $table->char('destination', 3);
            $table->date('departure_date');
            $table->date('return_date')->nullable();
            $table->string('trip_type', 20);
            $table->string('cabin', 24);
            $table->unsignedTinyInteger('adults');
            $table->unsignedTinyInteger('children')->default(0);
            $table->unsignedTinyInteger('infants')->default(0);
            $table->char('currency', 3);
            $table->unsignedInteger('result_count')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });

        Schema::create('travel_offers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('flight_search_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->index();
            // This value participates in a composite unique index. MySQL cannot
            // index an unbounded TEXT column without an explicit prefix length.
            $table->string('provider_reference', 512);
            $table->string('channel', 24)->default('consumer')->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('provider_total_minor');
            $table->unsignedBigInteger('markup_minor')->default(0);
            $table->unsignedBigInteger('selling_total_minor');
            $table->json('itinerary');
            $table->json('fare_summary')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_reference', 'flight_search_id'], 'offer_provider_search_unique');
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 24)->index();
            $table->string('status', 30)->default('draft')->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('fees_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->json('customer')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('travel_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_type', 24)->default('flight')->index();
            $table->string('provider', 40)->index();
            $table->string('provider_locator')->nullable()->index();
            $table->string('status', 30)->default('pending')->index();
            $table->json('travellers')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 40)->index();
            $table->string('gateway_reference')->nullable()->unique();
            $table->string('status', 30)->default('pending')->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_number')->nullable()->unique();
            $table->string('passenger_reference')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedTinyInteger('issuance_attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('travel_offers');
        Schema::dropIfExists('flight_searches');
    }
};

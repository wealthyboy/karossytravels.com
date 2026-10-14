<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flight_deals', function (Blueprint $table): void {
            $table->unsignedBigInteger('airline_id')->nullable()->change();
            $table->date('travel_from')->nullable()->change();
            $table->date('travel_until')->nullable()->change();
            $table->boolean('all_airlines')->default(false);
            $table->json('airline_codes')->nullable();
            $table->date('booking_from')->nullable();
            $table->date('booking_until')->nullable();
        });
        Schema::table('flight_offers', function (Blueprint $table): void {
            $table->foreignId('flight_deal_id')->nullable()->constrained('flight_deals')->nullOnDelete();
            $table->boolean('promotion_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('flight_offers', function (Blueprint $table): void {
            $table->dropForeign(['flight_deal_id']);
            $table->dropColumn(['flight_deal_id', 'promotion_enabled']);
        });
        // Keep nullable legacy columns: booking-only/all-airline rules may exist.
        Schema::table('flight_deals', function (Blueprint $table): void {
            $table->dropColumn(['all_airlines', 'airline_codes', 'booking_from', 'booking_until']);
        });
    }
};

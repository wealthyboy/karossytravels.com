<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_booking_settings', function (Blueprint $table): void {
            $table->id();
            $table->text('cardholder_name')->nullable();
            $table->text('card_brand')->nullable();
            $table->text('expiry_month')->nullable();
            $table->text('expiry_year')->nullable();
            $table->text('vault_reference')->nullable();
            $table->string('last_four', 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_booking_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
            $table->foreignUuid('travel_offer_id')->nullable()->change();
            $table->foreignUuid('hotel_offer_id')->nullable()->after('travel_offer_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hotel_offer_id');
            $table->foreignUuid('travel_offer_id')->nullable(false)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flight_offers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->char('origin_airport', 3);
            $table->string('origin_city', 100);
            $table->char('destination_airport', 3);
            $table->string('destination_city', 100);
            $table->string('airline_name', 120);
            $table->char('airline_code', 3)->nullable();
            $table->date('departure_date');
            $table->date('return_date');
            $table->string('cabin', 24)->default('economy');
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3)->default('USD');
            $table->string('image_path')->nullable();
            $table->text('image_url')->nullable();
            $table->string('label', 80)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'departure_date', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_offers');
    }
};

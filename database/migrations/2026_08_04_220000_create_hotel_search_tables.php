<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_searches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('session_id')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 24)->default('consumer')->index();
            $table->string('provider', 40)->index();
            $table->char('destination_code', 3)->index();
            $table->string('destination_label');
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedTinyInteger('adults');
            $table->unsignedTinyInteger('children')->default(0);
            $table->unsignedTinyInteger('rooms')->default(1);
            $table->char('currency', 3);
            $table->unsignedInteger('result_count')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });

        Schema::create('hotel_offers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('hotel_search_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->index();
            $table->string('hotel_code')->index();
            $table->string('name');
            $table->decimal('rating', 3, 1)->nullable();
            $table->json('location')->nullable();
            $table->json('amenities')->nullable();
            $table->string('room_name')->nullable();
            $table->string('rate_name')->nullable();
            $table->text('rate_key');
            $table->boolean('refundable')->default(false);
            $table->boolean('breakfast_included')->default(false);
            $table->char('currency', 3);
            $table->unsignedBigInteger('provider_total_minor');
            $table->unsignedBigInteger('markup_minor')->default(0);
            $table->unsignedBigInteger('selling_total_minor');
            $table->unsignedBigInteger('nightly_minor')->nullable();
            $table->json('pricing')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_offers');
        Schema::dropIfExists('hotel_searches');
    }
};

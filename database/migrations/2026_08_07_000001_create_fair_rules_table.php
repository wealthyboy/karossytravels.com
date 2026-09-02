<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fair_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('airline_code')->index();
            $table->string('title');
            $table->text('content')->nullable();
            $table->boolean('is_karossey_rule')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fair_rules');
    }
};

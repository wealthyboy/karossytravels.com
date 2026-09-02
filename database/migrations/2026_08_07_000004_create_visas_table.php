<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('country')->index();
            $table->integer('duration_days')->unsigned()->default(0);
            $table->bigInteger('fee_cents')->unsigned()->default(0);
            $table->text('requirements')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visas');
    }
};

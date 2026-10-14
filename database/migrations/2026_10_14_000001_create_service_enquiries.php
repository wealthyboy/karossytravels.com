<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_enquiries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference', 40)->unique();
            $table->string('type', 30)->index();
            $table->string('status', 30)->default('new')->index();
            $table->string('name', 160);
            $table->string('email', 190);
            $table->string('phone', 40);
            $table->longText('details');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_enquiries');
    }
};

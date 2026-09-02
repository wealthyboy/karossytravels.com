<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('product_type', 24)->unique();
            $table->string('markup_type', 20)->default('percentage');
            $table->decimal('markup_value', 12, 4)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('currency_settings', function (Blueprint $table): void {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name', 60);
            $table->string('symbol', 8);
            $table->decimal('manual_rate', 18, 6)->nullable();
            $table->string('adjustment_type', 20)->default('none');
            $table->decimal('adjustment_percent', 8, 4)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        DB::table('pricing_settings')->insert([
            ['product_type' => 'airline', 'markup_type' => 'percentage', 'markup_value' => null, 'currency' => 'USD', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['product_type' => 'hotel', 'markup_type' => 'percentage', 'markup_value' => null, 'currency' => 'USD', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('currency_settings')->insert([
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'manual_rate' => 1, 'adjustment_type' => 'none', 'adjustment_percent' => null, 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'manual_rate' => null, 'adjustment_type' => 'none', 'adjustment_percent' => null, 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_settings');
        Schema::dropIfExists('pricing_settings');
    }
};

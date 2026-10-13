<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_settings', function (Blueprint $table): void {
            $table->string('adjustment_type', 20)->default('markup');
        });
        foreach (['travel_offers', 'hotel_offers'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->unsignedBigInteger('markdown_minor')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::table('pricing_settings', fn (Blueprint $table) => $table->dropColumn('adjustment_type'));
        foreach (['travel_offers', 'hotel_offers'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('markdown_minor'));
        }
    }
};

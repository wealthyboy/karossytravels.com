<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('operator_markup_type', 20)->nullable()->after('fees_minor');
            $table->decimal('operator_markup_value', 12, 2)->nullable()->after('operator_markup_type');
            $table->unsignedBigInteger('operator_markup_minor')->default(0)->after('operator_markup_value');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['operator_markup_type', 'operator_markup_value', 'operator_markup_minor']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['account_type']);
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('account_type');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('account_type', 20)->default('b2c');
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->index('account_type');
        });
    }
};

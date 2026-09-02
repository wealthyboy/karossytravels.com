<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_type', 20)->default('b2c')->after('email');
            $table->string('company_name')->nullable()->after('account_type');
            $table->string('status', 20)->default('active')->after('company_name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['account_type', 'company_name', 'status']);
        });
    }
};

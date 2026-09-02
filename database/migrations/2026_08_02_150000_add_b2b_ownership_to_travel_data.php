<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('owner_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->index(['owner_user_id', 'status']);
        });

        Schema::table('flight_searches', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('session_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('flight_searches', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['owner_user_id', 'status']);
            $table->dropConstrainedForeignId('owner_user_id');
        });
    }
};

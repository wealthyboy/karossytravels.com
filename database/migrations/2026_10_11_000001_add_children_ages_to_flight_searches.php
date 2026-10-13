<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flight_searches', function (Blueprint $table): void {
            $table->json('children_ages')->nullable()->after('children');
        });
    }

    public function down(): void
    {
        Schema::table('flight_searches', function (Blueprint $table): void {
            $table->dropColumn('children_ages');
        });
    }
};

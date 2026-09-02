<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('source')->nullable()->after('order_id')->index();
            $table->string('referrer')->nullable()->after('source');
            $table->string('utm_source')->nullable()->after('referrer');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->string('affiliate_id')->nullable()->after('utm_campaign')->index();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['source', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'affiliate_id']);
        });
    }
};

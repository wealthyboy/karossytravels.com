<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('booking_hold_settings', function (Blueprint $table): void {
            $table->string('ngn_bank_name')->nullable()->after('sort_code');
            $table->string('ngn_account_name')->nullable()->after('ngn_bank_name');
            $table->string('ngn_account_number')->nullable()->after('ngn_account_name');
            $table->string('ngn_sort_code')->nullable()->after('ngn_account_number');
            $table->string('usd_bank_name')->nullable()->after('ngn_sort_code');
            $table->string('usd_account_name')->nullable()->after('usd_bank_name');
            $table->string('usd_account_number')->nullable()->after('usd_account_name');
            $table->string('usd_sort_code')->nullable()->after('usd_account_number');
        });

        DB::table('booking_hold_settings')
            ->select(['id', 'bank_name', 'account_name', 'account_number', 'sort_code'])
            ->orderBy('id')
            ->each(function (object $settings): void {
                DB::table('booking_hold_settings')->where('id', $settings->id)->update([
                    'ngn_bank_name' => $settings->bank_name,
                    'ngn_account_name' => $settings->account_name,
                    'ngn_account_number' => $settings->account_number,
                    'ngn_sort_code' => $settings->sort_code,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('booking_hold_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'ngn_bank_name',
                'ngn_account_name',
                'ngn_account_number',
                'ngn_sort_code',
                'usd_bank_name',
                'usd_account_name',
                'usd_account_number',
                'usd_sort_code',
            ]);
        });
    }
};

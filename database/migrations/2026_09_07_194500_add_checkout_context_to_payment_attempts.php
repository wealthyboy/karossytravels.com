<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('checkout_payment_attempts', 'customer_id')) {
            Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
                $table->foreignUuid('customer_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained()
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('checkout_payment_attempts', 'checkout_payload')) {
            Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
                $table->longText('checkout_payload')->nullable()->after('addon_ids');
            });
        }

        if (! Schema::hasColumn('checkout_payment_attempts', 'provider_locator')) {
            Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
                $table->string('provider_locator', 120)->nullable()->after('order_id')->index();
            });
        }

        if (! Schema::hasColumn('checkout_payment_attempts', 'failure_stage')) {
            Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
                $table->string('failure_stage', 40)->nullable()->after('provider_locator')->index();
            });
        }

        if (! Schema::hasColumn('checkout_payment_attempts', 'failure_message')) {
            Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
                $table->text('failure_message')->nullable()->after('failure_stage');
            });
        }
    }

    public function down(): void
    {
        // customer_id may pre-date this migration in existing installations,
        // so never remove it here.
        if (Schema::hasColumn('checkout_payment_attempts', 'failure_message')) {
            Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
                $table->dropColumn('failure_message');
            });
        }

        if (Schema::hasColumn('checkout_payment_attempts', 'failure_stage')) {
            Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
                $table->dropColumn('failure_stage');
            });
        }

        if (Schema::hasColumn('checkout_payment_attempts', 'provider_locator')) {
            Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
                $table->dropColumn('provider_locator');
            });
        }

        if (Schema::hasColumn('checkout_payment_attempts', 'checkout_payload')) {
            Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
                $table->dropColumn('checkout_payload');
            });
        }
    }
};

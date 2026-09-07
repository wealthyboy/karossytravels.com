<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
            $table->foreignUuid('customer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->longText('checkout_payload')->nullable()->after('addon_ids');
            $table->string('provider_locator', 120)->nullable()->after('order_id')->index();
            $table->string('failure_stage', 40)->nullable()->after('provider_locator')->index();
            $table->text('failure_message')->nullable()->after('failure_stage');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['provider_locator']);
            $table->dropIndex(['failure_stage']);
            $table->dropColumn(['customer_id', 'checkout_payload', 'provider_locator', 'failure_stage', 'failure_message']);
        });
    }
};

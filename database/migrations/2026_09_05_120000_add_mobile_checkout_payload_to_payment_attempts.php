<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
            $table->longText('checkout_payload')->nullable()->after('gateway_response');
            $table->char('client_token_hash', 64)->nullable()->index()->after('session_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
            $table->dropIndex(['client_token_hash']);
            $table->dropColumn(['checkout_payload', 'client_token_hash']);
        });
    }
};

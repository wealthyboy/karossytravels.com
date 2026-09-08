<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('checkout_payment_attempts', 'client_token_hash')) {
            Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
                $table->char('client_token_hash', 64)->nullable()->index()->after('session_fingerprint');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('checkout_payment_attempts', 'client_token_hash')) {
            Schema::table('checkout_payment_attempts', function (Blueprint $table): void {
                $table->dropIndex(['client_token_hash']);
                $table->dropColumn('client_token_hash');
            });
        }
    }
};

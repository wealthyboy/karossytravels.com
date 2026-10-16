<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table): void {
            $table->json('enquiry_emails')->nullable();
            $table->json('enquiry_cc_emails')->nullable();
            $table->json('enquiry_bcc_emails')->nullable();
        });
        foreach (['service_enquiries', 'partner_enquiries'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->json('email_delivery')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['service_enquiries', 'partner_enquiries'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('email_delivery'));
        }
        Schema::table('general_settings', fn (Blueprint $table) => $table->dropColumn(['enquiry_emails', 'enquiry_cc_emails', 'enquiry_bcc_emails']));
    }
};

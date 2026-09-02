<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visas', function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique()->after('id');
            $table->string('name')->nullable()->after('slug');
            $table->string('passport_country')->nullable()->index()->after('name');
            $table->char('passport_country_code', 2)->nullable()->after('passport_country');
            $table->char('destination_country_code', 2)->nullable()->after('country');
            $table->string('visa_type', 40)->default('sticker')->after('destination_country_code');
            $table->string('validity')->nullable()->after('duration_days');
            $table->string('processing_time')->nullable()->after('validity');
            $table->char('currency', 3)->default('NGN')->after('fee_cents');
            $table->unsignedBigInteger('consultation_fee_cents')->default(0)->after('currency');
            $table->text('summary')->nullable()->after('consultation_fee_cents');
            $table->json('requirements_list')->nullable()->after('requirements');
            $table->json('important_information')->nullable()->after('requirements_list');
            $table->boolean('featured')->default(false)->after('active');
        });

        Schema::create('visa_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('visa_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('payment_reference')->nullable()->unique();
            $table->string('session_fingerprint', 64)->index();
            $table->string('status', 30)->default('awaiting_payment')->index();
            $table->unsignedSmallInteger('travellers')->default(1);
            $table->boolean('consultation_added')->default(false);
            $table->char('currency', 3);
            $table->unsignedBigInteger('visa_total_minor');
            $table->unsignedBigInteger('consultation_total_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->longText('applicants');
            $table->longText('contact');
            $table->longText('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_enquiries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 30)->default('driver')->index();
            $table->string('status', 30)->default('new')->index();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40);
            $table->string('city');
            $table->string('vehicle_type')->nullable();
            $table->string('vehicle_year', 10)->nullable();
            $table->text('message')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('holiday_packages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('destination')->index();
            $table->string('country')->nullable();
            $table->string('tagline')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedSmallInteger('nights')->default(1);
            $table->unsignedSmallInteger('days')->default(2);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->string('image_path')->nullable();
            $table->json('inclusions')->nullable();
            $table->boolean('featured')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_packages');
        Schema::dropIfExists('partner_enquiries');
        Schema::dropIfExists('visa_applications');
        Schema::table('visas', function (Blueprint $table): void {
            $table->dropColumn([
                'slug', 'name', 'passport_country', 'passport_country_code', 'destination_country_code',
                'visa_type', 'validity', 'processing_time', 'currency', 'consultation_fee_cents', 'summary',
                'requirements_list', 'important_information', 'featured',
            ]);
        });
    }
};

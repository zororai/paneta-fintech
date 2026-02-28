<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_providers', function (Blueprint $table) {
            $table->id();
            $table->string('provider_type')->default('fx_provider'); // fx_provider, broker, exchange, etc.
            $table->string('company_name');
            $table->string('trading_name')->nullable();
            $table->string('registration_number')->unique();
            $table->string('tax_id')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone');
            $table->string('country');
            $table->text('address');
            $table->string('city');
            $table->string('postal_code');
            
            // Regulatory & Compliance
            $table->string('regulatory_body')->nullable();
            $table->string('license_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->json('regulatory_documents')->nullable(); // Store document paths
            $table->enum('verification_status', ['pending', 'under_review', 'verified', 'rejected'])->default('pending');
            $table->text('verification_notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            
            // Business Information
            $table->text('business_description')->nullable();
            $table->json('services_offered')->nullable(); // Array of services
            $table->json('supported_currencies')->nullable();
            $table->json('supported_countries')->nullable();
            $table->decimal('minimum_transaction', 15, 2)->default(0);
            $table->decimal('maximum_transaction', 15, 2)->nullable();
            $table->decimal('commission_rate', 5, 2)->default(0); // Percentage
            
            // Contact Person
            $table->string('contact_person_name');
            $table->string('contact_person_email');
            $table->string('contact_person_phone');
            $table->string('contact_person_position');
            
            // Banking Details
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_swift_code')->nullable();
            $table->string('bank_iban')->nullable();
            
            // Status & Settings
            $table->boolean('is_active')->default(false);
            $table->boolean('can_create_offers')->default(false);
            $table->boolean('can_execute_trades')->default(false);
            $table->boolean('auto_approve_trades')->default(false);
            $table->integer('rating')->default(0); // 0-5 stars
            $table->integer('total_trades')->default(0);
            $table->decimal('total_volume', 20, 2)->default(0);
            
            // Security
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_providers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add onboarding_stage to users table
        Schema::table('users', function (Blueprint $table) {
            $table->enum('onboarding_stage', [
                'registered',
                'email_verified',
                'contact_verified',
                'basic_access',
                'kyc_submitted',
                'kyc_verified',
                'risk_tiered',
                'first_transaction',
                'fully_onboarded',
                'suspended',
                'closed'
            ])->default('registered')->after('role');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_stage');
            $table->timestamp('last_reverification_at')->nullable();
            $table->timestamp('next_reverification_at')->nullable();
        });

        // KYB Documents table for merchant/business verification
        Schema::create('kyb_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('document_type', [
                'certificate_of_incorporation',
                'business_registration',
                'tax_certificate',
                'proof_of_address',
                'shareholder_register',
                'director_id',
                'bank_statement',
                'financial_statement',
                'license',
                'memorandum_of_association',
                'other'
            ]);
            $table->string('document_number')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->integer('file_size')->nullable();
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected', 'expired'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('issuing_authority')->nullable();
            $table->string('issuing_country', 2)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('extracted_data')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'document_type']);
            $table->index(['merchant_id', 'status']);
            $table->index('status');
        });

        // Onboarding progress tracking
        Schema::create('onboarding_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('step');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped', 'failed'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('data')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'step']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_progress');
        Schema::dropIfExists('kyb_documents');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'onboarding_stage',
                'onboarding_completed_at',
                'last_reverification_at',
                'next_reverification_at',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('compliance_cases')) {
            Schema::create('compliance_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_reference')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('related'); // transaction_intent, cross_border_transaction_intent, etc.
            $table->enum('type', [
                'aml_alert',
                'sanctions_hit',
                'pep_match',
                'adverse_media',
                'unusual_activity',
                'threshold_breach',
                'kyc_review',
                'kyb_review',
                'fraud_suspicion',
                'regulatory_inquiry',
                'sar_filing',
                'other'
            ]);
            $table->enum('status', [
                'open',
                'under_investigation',
                'pending_info',
                'escalated',
                'sar_filed',
                'closed_no_action',
                'closed_action_taken',
                'closed_false_positive'
            ])->default('open');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('risk_level', ['low', 'medium', 'high', 'very_high'])->default('medium');
            $table->text('description');
            $table->text('investigation_notes')->nullable();
            $table->text('resolution_summary')->nullable();
            $table->string('action_taken')->nullable();
            $table->decimal('amount_involved', 18, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('escalated_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sar_reference')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('evidence_ids')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
            $table->index('due_date');
            $table->index('created_at');
        });
        }

        if (!Schema::hasTable('compliance_case_notes')) {
            Schema::create('compliance_case_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->enum('note_type', ['investigation', 'decision', 'escalation', 'closure', 'general'])->default('general');
            $table->boolean('is_confidential')->default(false);
            $table->timestamps();

            $table->index('compliance_case_id');
            });
        }

        if (!Schema::hasTable('sanctions_lists')) {
            Schema::create('sanctions_lists', function (Blueprint $table) {
            $table->id();
            $table->string('list_code')->unique();
            $table->string('list_name');
            $table->string('source_authority');
            $table->string('source_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_updated_at')->nullable();
            $table->integer('entry_count')->default(0);
            $table->timestamps();
            });
        }

        if (!Schema::hasTable('sanctions_entries')) {
            Schema::create('sanctions_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sanctions_list_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->enum('entity_type', ['individual', 'organization', 'vessel', 'aircraft', 'other']);
            $table->string('primary_name');
            $table->json('aliases')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->json('nationalities')->nullable();
            $table->json('addresses')->nullable();
            $table->json('identifiers')->nullable(); // passport, id numbers, etc.
            $table->text('notes')->nullable();
            $table->date('listed_date')->nullable();
            $table->string('program')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['sanctions_list_id', 'entity_type']);
            $table->index('primary_name');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions_entries');
        Schema::dropIfExists('sanctions_lists');
        Schema::dropIfExists('compliance_case_notes');
        Schema::dropIfExists('compliance_cases');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('disputable'); // transaction_intent, cross_border_transaction_intent, payment_request
            $table->enum('status', [
                'opened',
                'under_review',
                'evidence_requested',
                'evidence_submitted',
                'escalated',
                'resolved_in_favor',
                'resolved_against',
                'withdrawn',
                'expired'
            ])->default('opened');
            $table->enum('type', [
                'unauthorized_transaction',
                'duplicate_charge',
                'amount_discrepancy',
                'service_not_rendered',
                'product_not_received',
                'quality_issue',
                'refund_not_received',
                'other'
            ]);
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->decimal('disputed_amount', 18, 2);
            $table->string('currency', 3);
            $table->text('description');
            $table->text('resolution_notes')->nullable();
            $table->decimal('resolved_amount', 18, 2)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evidence_deadline')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['user_id', 'status']);
            $table->index('evidence_deadline');
            $table->index('created_at');
        });

        Schema::create('dispute_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['document', 'screenshot', 'correspondence', 'receipt', 'other']);
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('dispute_id');
        });

        Schema::create('dispute_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('comment');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index('dispute_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_comments');
        Schema::dropIfExists('dispute_evidence');
        Schema::dropIfExists('disputes');
    }
};

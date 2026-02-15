<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_reference')->unique();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'partially_completed'])->default('pending');
            $table->enum('batch_type', ['merchant_payout', 'fx_settlement', 'refund_batch', 'fee_collection'])->default('merchant_payout');
            $table->string('currency', 3);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('total_fees', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->integer('successful_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['batch_type', 'status']);
            $table->index('created_at');
        });

        Schema::create('settlement_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_batch_id')->constrained()->cascadeOnDelete();
            $table->morphs('settleable'); // transaction_intent, cross_border_transaction_intent, etc.
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->decimal('fee', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2);
            $table->string('currency', 3);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('reference')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['settlement_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_batch_items');
        Schema::dropIfExists('settlement_batches');
    }
};

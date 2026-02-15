<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aggregated_transactions')) {
            return; // Table already exists from previous migration
        }
        
        Schema::create('aggregated_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('linked_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->nullable();
            $table->string('normalized_id')->unique();
            $table->enum('type', ['credit', 'debit', 'transfer', 'payment', 'refund', 'fee', 'interest', 'other'])->default('other');
            $table->enum('category', [
                'income', 'salary', 'transfer_in', 'refund',
                'shopping', 'groceries', 'utilities', 'transport', 'entertainment',
                'health', 'education', 'travel', 'restaurant', 'subscription',
                'transfer_out', 'bill_payment', 'loan_payment', 'investment',
                'fee', 'tax', 'other'
            ])->default('other');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->decimal('original_amount', 18, 2)->nullable();
            $table->string('original_currency', 3)->nullable();
            $table->string('description')->nullable();
            $table->string('normalized_description')->nullable();
            $table->string('merchant_name')->nullable();
            $table->string('merchant_category_code')->nullable();
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_account')->nullable();
            $table->string('reference')->nullable();
            $table->enum('status', ['pending', 'posted', 'cancelled', 'failed'])->default('posted');
            $table->decimal('running_balance', 18, 2)->nullable();
            $table->timestamp('transaction_date');
            $table->timestamp('posted_date')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'transaction_date']);
            $table->index(['linked_account_id', 'transaction_date']);
            $table->index(['user_id', 'category']);
            $table->index(['institution_id', 'external_id']);
            $table->index('normalized_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aggregated_transactions');
    }
};

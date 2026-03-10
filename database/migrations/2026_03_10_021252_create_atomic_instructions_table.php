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
        Schema::create('atomic_instructions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_transaction_id')->constrained('escrow_transactions')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('source_account_id')->constrained('linked_accounts');
            $table->foreignId('destination_account_id')->constrained('linked_accounts');
            $table->string('instruction_type')->comment('initiator_send or counterparty_send');
            $table->string('currency', 3);
            $table->decimal('amount', 15, 2);
            $table->decimal('fee', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->json('instruction_payload')->comment('Signed instruction data');
            $table->string('signed_hash');
            $table->enum('status', ['generated', 'sent_to_institution', 'acknowledged', 'executed', 'settled', 'failed'])->default('generated');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->text('institution_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            
            $table->index(['escrow_transaction_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atomic_instructions');
    }
};

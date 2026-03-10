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
        Schema::create('escrow_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exchange_request_id');
            $table->unsignedBigInteger('initiator_user_id');
            $table->unsignedBigInteger('counterparty_user_id');
            $table->unsignedBigInteger('init_source_acct_id');
            $table->unsignedBigInteger('init_dest_acct_id');
            $table->unsignedBigInteger('cp_source_acct_id');
            $table->unsignedBigInteger('cp_dest_acct_id');
            $table->string('initiator_currency', 3);
            $table->decimal('initiator_amount', 15, 2);
            $table->decimal('initiator_fee', 15, 2);
            $table->decimal('initiator_total', 15, 2);
            $table->string('counterparty_currency', 3);
            $table->decimal('counterparty_amount', 15, 2);
            $table->decimal('counterparty_fee', 15, 2);
            $table->decimal('counterparty_total', 15, 2);
            $table->decimal('exchange_rate', 15, 6);
            $table->enum('status', ['precondition_check', 'awaiting_confirmation', 'confirmed', 'executing', 'completed', 'failed'])->default('precondition_check');
            $table->boolean('initiator_confirmed')->default(false);
            $table->boolean('counterparty_confirmed')->default(false);
            $table->timestamp('initiator_confirmed_at')->nullable();
            $table->timestamp('counterparty_confirmed_at')->nullable();
            $table->json('precondition_checks')->nullable()->comment('Balance, AML, sanctions, jurisdiction checks');
            $table->boolean('preconditions_passed')->default(false);
            $table->text('failure_reason')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->foreign('exchange_request_id')->references('id')->on('p2p_exchange_requests')->onDelete('cascade');
            $table->foreign('initiator_user_id')->references('id')->on('users');
            $table->foreign('counterparty_user_id')->references('id')->on('users');
            $table->foreign('init_source_acct_id')->references('id')->on('linked_accounts');
            $table->foreign('init_dest_acct_id')->references('id')->on('linked_accounts');
            $table->foreign('cp_source_acct_id')->references('id')->on('linked_accounts');
            $table->foreign('cp_dest_acct_id')->references('id')->on('linked_accounts');
            
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escrow_transactions');
    }
};

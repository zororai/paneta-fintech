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
        Schema::create('p2p_exchange_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('counterparty_user_id');
            $table->unsignedBigInteger('initiator_user_id');
            $table->string('counterparty_id_number')->comment('Platform-generated unique ID');
            $table->unsignedBigInteger('cp_source_account_id');
            $table->unsignedBigInteger('cp_dest_account_id');
            $table->string('sell_currency', 3);
            $table->decimal('sell_amount', 15, 2);
            $table->string('buy_currency', 3);
            $table->decimal('buy_amount', 15, 2);
            $table->decimal('exchange_rate', 15, 6);
            $table->enum('status', ['pending', 'accepted', 'declined', 'expired', 'completed', 'failed'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->foreign('offer_id')->references('id')->on('p2p_offers')->onDelete('cascade');
            $table->foreign('counterparty_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('initiator_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cp_source_account_id')->references('id')->on('linked_accounts');
            $table->foreign('cp_dest_account_id')->references('id')->on('linked_accounts');
            
            $table->index(['counterparty_user_id', 'status']);
            $table->index(['initiator_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('p2p_exchange_requests');
    }
};

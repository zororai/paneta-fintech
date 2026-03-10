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
        Schema::create('p2p_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('source_account_id')->constrained('linked_accounts');
            $table->foreignId('destination_account_id')->constrained('linked_accounts');
            $table->string('sell_currency', 3);
            $table->string('buy_currency', 3);
            $table->decimal('rate', 15, 6);
            $table->decimal('amount', 15, 2);
            $table->decimal('min_amount', 15, 2);
            $table->json('settlement_methods');
            $table->enum('status', ['active', 'paused', 'expired', 'completed'])->default('active');
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['sell_currency', 'buy_currency', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('p2p_offers');
    }
};

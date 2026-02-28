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
        Schema::create('fx_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('provider_id')->constrained('institutions')->onDelete('cascade');
            $table->foreignId('source_account_id')->constrained('linked_accounts')->onDelete('cascade');
            $table->foreignId('destination_account_id')->constrained('linked_accounts')->onDelete('cascade');
            $table->string('source_currency', 3);
            $table->string('destination_currency', 3);
            $table->decimal('source_amount', 15, 2);
            $table->decimal('exchange_rate', 15, 8);
            $table->decimal('destination_amount', 15, 2);
            $table->decimal('paneta_fee', 15, 2);
            $table->decimal('provider_fee', 15, 2);
            $table->decimal('total_fees', 15, 2);
            $table->decimal('net_amount_debited', 15, 2);
            $table->string('settlement_preference');
            $table->string('status')->default('pending');
            $table->string('transaction_type')->default('fx_marketplace');
            $table->text('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fx_transactions');
    }
};

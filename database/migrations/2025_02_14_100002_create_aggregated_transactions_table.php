<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aggregated_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aggregated_account_id')->constrained()->onDelete('cascade');
            $table->string('external_reference')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('description')->nullable();
            $table->string('transaction_type')->nullable();
            $table->timestamp('transaction_date');
            $table->timestamps();

            $table->index(['aggregated_account_id', 'transaction_date'], 'agg_txn_account_date_idx');
            $table->unique(['aggregated_account_id', 'external_reference'], 'agg_txn_account_ext_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aggregated_transactions');
    }
};

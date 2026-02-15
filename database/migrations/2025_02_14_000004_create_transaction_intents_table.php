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
        Schema::create('transaction_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('issuer_account_id')->constrained('linked_accounts')->onDelete('cascade');
            $table->string('acquirer_identifier');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->enum('status', ['pending', 'confirmed', 'executed', 'failed'])->default('pending');
            $table->string('reference')->unique();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_intents');
    }
};

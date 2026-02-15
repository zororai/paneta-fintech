<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cross_border_transaction_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('source_account_id')->constrained('linked_accounts')->onDelete('cascade');
            $table->string('destination_identifier');
            $table->string('destination_country', 2)->nullable();
            $table->string('source_currency', 3);
            $table->string('destination_currency', 3);
            $table->decimal('source_amount', 18, 2);
            $table->decimal('destination_amount', 18, 2);
            $table->decimal('fx_rate', 18, 8);
            $table->foreignId('fx_provider_id')->nullable()->constrained('fx_providers');
            $table->foreignId('fx_quote_id')->nullable()->constrained('fx_quotes');
            $table->decimal('fee_amount', 18, 2)->default(0);
            $table->string('fee_currency', 3)->nullable();
            $table->enum('status', ['pending', 'fx_locked', 'source_debited', 'fx_executed', 'destination_credited', 'completed', 'failed', 'rolled_back'])->default('pending');
            $table->string('reference')->unique();
            $table->string('idempotency_key')->nullable()->unique();
            $table->json('leg_statuses')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cross_border_transaction_intents');
    }
};

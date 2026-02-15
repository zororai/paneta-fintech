<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('source_account_id')->constrained('linked_accounts');
            $table->string('sell_currency', 3);
            $table->string('buy_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->decimal('amount', 18, 2);
            $table->decimal('min_amount', 18, 2)->nullable();
            $table->decimal('filled_amount', 18, 2)->default(0);
            $table->enum('status', ['open', 'partially_filled', 'matched', 'executed', 'cancelled', 'expired', 'failed'])->default('open');
            $table->foreignId('matched_offer_id')->nullable()->constrained('fx_offers');
            $table->foreignId('matched_user_id')->nullable()->constrained('users');
            $table->timestamp('expires_at')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['sell_currency', 'buy_currency', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_offers');
    }
};

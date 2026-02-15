<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('linked_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_name');
            $table->string('account_number')->nullable();
            $table->enum('account_type', ['brokerage', 'retirement', 'isa', 'pension', 'trust', 'custodial', 'other'])->default('brokerage');
            $table->string('currency', 3)->default('USD');
            $table->decimal('total_value', 18, 2)->default(0);
            $table->decimal('cash_balance', 18, 2)->default(0);
            $table->decimal('invested_value', 18, 2)->default(0);
            $table->decimal('unrealized_gain_loss', 18, 2)->default(0);
            $table->decimal('day_change', 18, 2)->default(0);
            $table->decimal('day_change_percent', 8, 4)->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->json('holdings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'account_type']);
        });

        Schema::create('historical_prices', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20);
            $table->enum('asset_type', ['stock', 'etf', 'mutual_fund', 'bond', 'crypto', 'commodity', 'currency', 'other'])->default('stock');
            $table->string('exchange', 20)->nullable();
            $table->decimal('open_price', 18, 6)->nullable();
            $table->decimal('high_price', 18, 6)->nullable();
            $table->decimal('low_price', 18, 6)->nullable();
            $table->decimal('close_price', 18, 6);
            $table->decimal('adjusted_close', 18, 6)->nullable();
            $table->bigInteger('volume')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->date('price_date');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['symbol', 'price_date']);
            $table->index(['symbol', 'price_date']);
            $table->index(['asset_type', 'price_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_prices');
        Schema::dropIfExists('investment_accounts');
    }
};

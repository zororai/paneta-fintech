<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add consent_scopes to institutions if not exists
        if (!Schema::hasColumn('institutions', 'consent_scopes')) {
            Schema::table('institutions', function (Blueprint $table) {
                $table->json('consent_scopes')->nullable()->after('type');
                $table->json('capabilities')->nullable()->after('consent_scopes');
                $table->string('logo_url')->nullable()->after('name');
            });
        }

        // Wealth Account table
        if (!Schema::hasTable('wealth_accounts')) {
            Schema::create('wealth_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('institution_id')->constrained()->onDelete('cascade');
                $table->foreignId('linked_account_id')->constrained()->onDelete('cascade');
                $table->string('account_type');
                $table->string('account_name')->nullable();
                $table->string('currency', 3);
                $table->decimal('total_value', 18, 2)->default(0);
                $table->timestamp('last_synced_at')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        // Wealth Holding table
        if (!Schema::hasTable('wealth_holdings')) {
            Schema::create('wealth_holdings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wealth_account_id')->constrained()->onDelete('cascade');
                $table->string('asset_class');
                $table->string('symbol')->nullable();
                $table->string('name');
                $table->decimal('quantity', 18, 8);
                $table->decimal('current_price', 18, 4);
                $table->string('price_currency', 3);
                $table->decimal('market_value', 18, 2);
                $table->decimal('cost_basis', 18, 2)->nullable();
                $table->decimal('unrealized_pnl', 18, 2)->nullable();
                $table->decimal('allocation_pct', 5, 2)->default(0);
                $table->string('sector')->nullable();
                $table->string('region')->nullable();
                $table->timestamp('price_updated_at')->nullable();
                $table->timestamps();
            });
        }

        // Wealth Analytics table
        if (!Schema::hasTable('wealth_analytics')) {
            Schema::create('wealth_analytics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->decimal('total_portfolio_value', 18, 2);
                $table->string('base_currency', 3);
                $table->decimal('risk_score', 5, 2)->nullable();
                $table->decimal('twr', 8, 4)->nullable();
                $table->decimal('irr', 8, 4)->nullable();
                $table->json('asset_allocation')->nullable();
                $table->json('currency_exposure')->nullable();
                $table->json('sector_exposure')->nullable();
                $table->json('geographic_exposure')->nullable();
                $table->decimal('volatility', 8, 4)->nullable();
                $table->timestamp('calculated_at');
                $table->timestamps();
            });
        }

        // Wealth Snapshot table
        if (!Schema::hasTable('wealth_snapshots')) {
            Schema::create('wealth_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->date('snapshot_date');
                $table->decimal('total_value', 18, 2);
                $table->string('currency', 3);
                $table->json('holdings_summary')->nullable();
                $table->json('allocation_summary')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'snapshot_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wealth_snapshots');
        Schema::dropIfExists('wealth_analytics');
        Schema::dropIfExists('wealth_holdings');
        Schema::dropIfExists('wealth_accounts');
        Schema::dropIfExists('fx_settlements');
        Schema::dropIfExists('fx_transaction_intents');
        Schema::dropIfExists('fx_quotes');

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['consent_scopes', 'capabilities', 'logo_url']);
        });
    }
};

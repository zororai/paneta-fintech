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
        Schema::table('transaction_intents', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'ti_user_created_idx');
            $table->index('status', 'ti_status_idx');
            $table->index('reference', 'ti_reference_idx');
        });

        Schema::table('cross_border_transaction_intents', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'cbti_user_created_idx');
            $table->index('status', 'cbti_status_idx');
            $table->index('reference', 'cbti_reference_idx');
        });

        Schema::table('fx_quotes', function (Blueprint $table) {
            $table->index(['base_currency', 'quote_currency', 'expires_at'], 'fxq_currencies_expires_idx');
        });

        Schema::table('security_logs', function (Blueprint $table) {
            $table->index(['event_type', 'created_at'], 'sl_event_created_idx');
            $table->index('severity', 'sl_severity_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['entity_type', 'entity_id'], 'al_entity_idx');
            $table->index('created_at', 'al_created_idx');
        });

        Schema::table('fx_offers', function (Blueprint $table) {
            $table->index(['sell_currency', 'buy_currency', 'status'], 'fxo_currencies_status_idx');
            $table->index(['status', 'expires_at'], 'fxo_status_expires_idx');
        });

        Schema::table('payment_requests', function (Blueprint $table) {
            $table->index(['status', 'expires_at'], 'pr_status_expires_idx');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['status', 'expires_at'], 'sub_status_expires_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_intents', function (Blueprint $table) {
            $table->dropIndex('ti_user_created_idx');
            $table->dropIndex('ti_status_idx');
            $table->dropIndex('ti_reference_idx');
        });

        Schema::table('cross_border_transaction_intents', function (Blueprint $table) {
            $table->dropIndex('cbti_user_created_idx');
            $table->dropIndex('cbti_status_idx');
            $table->dropIndex('cbti_reference_idx');
        });

        Schema::table('fx_quotes', function (Blueprint $table) {
            $table->dropIndex('fxq_currencies_expires_idx');
        });

        Schema::table('security_logs', function (Blueprint $table) {
            $table->dropIndex('sl_event_created_idx');
            $table->dropIndex('sl_severity_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('al_entity_idx');
            $table->dropIndex('al_created_idx');
        });

        Schema::table('fx_offers', function (Blueprint $table) {
            $table->dropIndex('fxo_currencies_status_idx');
            $table->dropIndex('fxo_status_expires_idx');
        });

        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropIndex('pr_status_expires_idx');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('sub_status_expires_idx');
        });
    }
};

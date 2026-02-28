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
        Schema::table('fx_offers', function (Blueprint $table) {
            $table->foreignId('destination_account_id')->nullable()->after('source_account_id')->constrained('linked_accounts')->onDelete('set null');
            $table->json('settlement_methods')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fx_offers', function (Blueprint $table) {
            $table->dropForeign(['destination_account_id']);
            $table->dropColumn(['destination_account_id', 'settlement_methods']);
        });
    }
};

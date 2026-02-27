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
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('business_sector')->nullable()->after('business_type');
            $table->string('tax_id')->nullable()->after('country');
            $table->string('business_logo')->nullable()->after('tax_id');
            $table->string('reporting_currency', 3)->nullable()->after('default_currency');
            $table->json('other_settlement_accounts')->nullable()->after('settlement_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn([
                'business_sector',
                'tax_id',
                'business_logo',
                'reporting_currency',
                'other_settlement_accounts'
            ]);
        });
    }
};

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
        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->string('account_holder_name')->nullable()->after('account_identifier');
            $table->string('country', 2)->nullable()->after('institution_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->dropColumn(['account_holder_name', 'country']);
        });
    }
};

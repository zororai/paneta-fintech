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
            $table->text('description')->nullable()->after('currency');
            $table->string('destination_country', 2)->nullable()->after('description');
            $table->foreignId('destination_institution_id')->nullable()->constrained('institutions')->onDelete('set null')->after('destination_country');
            $table->string('destination_currency', 3)->nullable()->after('destination_institution_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_intents', function (Blueprint $table) {
            $table->dropForeign(['destination_institution_id']);
            $table->dropColumn(['description', 'destination_country', 'destination_institution_id', 'destination_currency']);
        });
    }
};

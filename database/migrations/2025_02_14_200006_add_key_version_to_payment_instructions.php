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
        Schema::table('payment_instructions', function (Blueprint $table) {
            $table->integer('key_version')->default(1)->after('signed_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_instructions', function (Blueprint $table) {
            $table->dropColumn('key_version');
        });
    }
};

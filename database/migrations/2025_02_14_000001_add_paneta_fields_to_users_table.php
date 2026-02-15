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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('kyc_status', ['pending', 'verified'])->default('pending')->after('password');
            $table->enum('risk_tier', ['low', 'medium', 'high'])->default('low')->after('kyc_status');
            $table->enum('role', ['user', 'admin'])->default('user')->after('risk_tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['kyc_status', 'risk_tier', 'role']);
        });
    }
};

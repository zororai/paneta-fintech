<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_intents', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->unique()->after('reference');
        });

        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->json('consent_scope')->nullable()->after('consent_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_intents', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });

        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->dropColumn('consent_scope');
        });
    }
};

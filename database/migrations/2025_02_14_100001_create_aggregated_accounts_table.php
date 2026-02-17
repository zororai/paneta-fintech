<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aggregated_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('institution_id')->constrained()->onDelete('cascade');
            $table->string('external_account_id');
            $table->string('currency', 3);
            $table->decimal('available_balance', 18, 2)->default(0);
            $table->timestamp('last_refreshed_at')->nullable();
            $table->enum('status', ['active', 'stale', 'error', 'disconnected'])->default('active');
            $table->timestamps();

            $table->unique(['user_id', 'institution_id', 'external_account_id'], 'agg_accounts_user_inst_ext_unique');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aggregated_accounts');
    }
};

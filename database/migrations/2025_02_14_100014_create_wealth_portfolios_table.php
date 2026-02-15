<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wealth_portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('total_value', 18, 2)->default(0);
            $table->string('base_currency', 3)->default('USD');
            $table->json('asset_allocation')->nullable();
            $table->json('currency_allocation')->nullable();
            $table->decimal('risk_score', 5, 2)->nullable();
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wealth_portfolios');
    }
};

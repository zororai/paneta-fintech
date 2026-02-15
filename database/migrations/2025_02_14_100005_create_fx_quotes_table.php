<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fx_provider_id')->constrained('fx_providers')->onDelete('cascade');
            $table->string('base_currency', 3);
            $table->string('quote_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->decimal('bid_rate', 18, 8)->nullable();
            $table->decimal('ask_rate', 18, 8)->nullable();
            $table->decimal('spread_percentage', 5, 4)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['base_currency', 'quote_currency', 'expires_at']);
            $table->index(['fx_provider_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_quotes');
    }
};

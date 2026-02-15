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
        Schema::create('currency_balances', function (Blueprint $table) {
            $table->id();
            $table->string('currency', 3)->unique();
            $table->decimal('total_fees_collected', 18, 8)->default(0);
            $table->decimal('total_refunded', 18, 8)->default(0);
            $table->decimal('total_adjustments', 18, 8)->default(0);
            $table->decimal('net_position', 18, 8)->default(0);
            $table->timestamps();

            $table->index('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_balances');
    }
};

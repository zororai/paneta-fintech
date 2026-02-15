<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('transaction_type');
            $table->unsignedBigInteger('transaction_id');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->decimal('fee_percentage', 5, 4);
            $table->string('fee_type')->default('platform');
            $table->enum('status', ['pending', 'collected', 'refunded'])->default('collected');
            $table->timestamps();

            $table->index(['transaction_type', 'transaction_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_ledger');
    }
};

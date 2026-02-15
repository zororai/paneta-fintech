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
        Schema::create('payment_instructions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_intent_id')->constrained()->onDelete('cascade');
            $table->json('instruction_payload');
            $table->string('signed_hash', 64);
            $table->enum('status', ['generated', 'sent', 'confirmed'])->default('generated');
            $table->timestamps();

            $table->index('transaction_intent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_instructions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('linked_account_id')->nullable()->constrained('linked_accounts');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->decimal('amount_received', 18, 2)->default(0);
            $table->enum('status', ['pending', 'partially_fulfilled', 'completed', 'expired', 'cancelled'])->default('pending');
            $table->string('reference')->unique();
            $table->string('qr_code_data')->nullable();
            $table->string('description')->nullable();
            $table->boolean('allow_partial')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['reference']);
            $table->index(['expires_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};

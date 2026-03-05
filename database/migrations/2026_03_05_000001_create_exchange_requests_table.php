<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('fx_provider_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_source_account_id')->nullable()->constrained('linked_accounts');
            $table->foreignId('user_destination_account_id')->nullable()->constrained('linked_accounts');
            $table->foreignId('provider_source_account_id')->nullable()->constrained('linked_accounts');
            
            // Exchange details
            $table->string('sell_currency', 3);
            $table->string('buy_currency', 3);
            $table->decimal('sell_amount', 18, 2);
            $table->decimal('buy_amount', 18, 2);
            $table->decimal('exchange_rate', 18, 8);
            
            // Fees
            $table->decimal('provider_fee', 18, 2)->default(0);
            $table->decimal('platform_fee', 18, 2)->default(0);
            $table->decimal('total_fees', 18, 2)->default(0);
            
            // Status tracking
            $table->enum('status', [
                'pending',           // Waiting for FX Provider response
                'accepted',          // FX Provider accepted, waiting for user payment
                'rejected',          // FX Provider rejected
                'user_paid',         // User confirmed payment
                'provider_paid',     // FX Provider completed payment
                'completed',         // Exchange completed
                'cancelled',         // Cancelled by user
                'failed'            // Failed/expired
            ])->default('pending');
            
            // Timestamps for workflow tracking
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('user_paid_at')->nullable();
            $table->timestamp('provider_paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            // Additional info
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('reference_number')->unique();
            
            $table->timestamps();
            
            $table->index(['fx_provider_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_requests');
    }
};

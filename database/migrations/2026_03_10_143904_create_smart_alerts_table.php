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
        Schema::create('smart_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('currency_pair', 10);
            $table->decimal('target_rate', 15, 6);
            $table->enum('alert_type', ['above', 'below', 'change_1_percent']);
            $table->boolean('email_notifications')->default(false);
            $table->boolean('sms_notifications')->default(false);
            $table->boolean('push_notifications')->default(false);
            $table->enum('status', ['active', 'triggered', 'expired', 'deleted'])->default('active');
            $table->timestamp('triggered_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['currency_pair', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smart_alerts');
    }
};

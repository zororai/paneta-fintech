<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_rails', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('provider');
            $table->enum('type', ['bank_transfer', 'card_network', 'mobile_money', 'wallet', 'crypto', 'swift', 'sepa', 'ach', 'rtp']);
            $table->json('supported_currencies');
            $table->json('supported_countries');
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_instant')->default(false);
            $table->boolean('supports_scheduled')->default(true);
            $table->decimal('min_amount', 18, 2)->nullable();
            $table->decimal('max_amount', 18, 2)->nullable();
            $table->decimal('base_fee', 18, 4)->default(0);
            $table->decimal('percentage_fee', 8, 4)->default(0);
            $table->integer('typical_settlement_minutes')->default(1440); // 24 hours default
            $table->integer('priority')->default(50); // Lower = higher priority
            $table->decimal('reliability_score', 5, 2)->default(100.00);
            $table->json('operating_hours')->nullable(); // {"mon": {"start": "08:00", "end": "18:00"}, ...}
            $table->json('metadata')->nullable();
            $table->timestamp('last_health_check')->nullable();
            $table->enum('health_status', ['healthy', 'degraded', 'unhealthy', 'maintenance'])->default('healthy');
            $table->timestamps();

            $table->index(['is_active', 'health_status']);
            $table->index('type');
        });

        Schema::create('rail_availability_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_rail_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['available', 'degraded', 'unavailable', 'maintenance']);
            $table->integer('response_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['payment_rail_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rail_availability_logs');
        Schema::dropIfExists('payment_rails');
    }
};

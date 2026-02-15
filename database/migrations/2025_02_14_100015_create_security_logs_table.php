<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->enum('event_type', [
                'login_success',
                'login_failed',
                'account_locked',
                'suspicious_activity',
                'rate_limit_exceeded',
                'invalid_token',
                'password_reset',
                'mfa_enabled',
                'mfa_disabled',
                'consent_granted',
                'consent_revoked'
            ]);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info');
            $table->timestamp('created_at');

            $table->index(['user_id', 'event_type']);
            $table->index(['event_type', 'severity', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};

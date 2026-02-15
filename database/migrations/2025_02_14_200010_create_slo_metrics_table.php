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
        Schema::create('slo_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('operation');
            $table->integer('response_time_ms');
            $table->boolean('success');
            $table->string('error_code')->nullable();
            $table->string('endpoint')->nullable();
            $table->string('method')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');

            $table->index(['operation', 'recorded_at']);
            $table->index(['success', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slo_metrics');
    }
};

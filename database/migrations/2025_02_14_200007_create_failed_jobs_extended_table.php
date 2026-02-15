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
        Schema::create('dead_letter_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('queue');
            $table->string('job_class');
            $table->longText('payload');
            $table->longText('exception');
            $table->integer('attempts');
            $table->timestamp('failed_at');
            $table->timestamp('last_retry_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->enum('status', ['pending_review', 'retrying', 'resolved', 'abandoned'])->default('pending_review');
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->string('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'failed_at']);
            $table->index('queue');
            $table->index('job_class');
            
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dead_letter_jobs');
    }
};

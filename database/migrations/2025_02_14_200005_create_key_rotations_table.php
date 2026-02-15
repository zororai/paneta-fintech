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
        Schema::create('key_rotations', function (Blueprint $table) {
            $table->id();
            $table->string('key_type');
            $table->integer('version');
            $table->string('key_hash');
            $table->enum('status', ['active', 'deprecated', 'revoked'])->default('active');
            $table->timestamp('activated_at');
            $table->timestamp('deprecated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('rotated_by')->nullable();
            $table->string('rotation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['key_type', 'version']);
            $table->index(['key_type', 'status']);
            $table->index('expires_at');
            
            $table->foreign('rotated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('key_rotations');
    }
};

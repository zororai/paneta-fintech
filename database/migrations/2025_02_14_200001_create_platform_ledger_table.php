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
        Schema::create('platform_ledger', function (Blueprint $table) {
            $table->id();
            $table->enum('entry_type', ['fee', 'refund', 'adjustment', 'write_off']);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('amount', 18, 8);
            $table->string('currency', 3);
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entry_type', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['currency', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_ledger');
    }
};

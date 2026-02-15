<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('country', 2);
            $table->boolean('is_active')->default(true);
            $table->integer('risk_score')->default(50);
            $table->decimal('default_spread_percentage', 5, 4)->default(0.5);
            $table->json('supported_pairs')->nullable();
            $table->string('api_endpoint')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'risk_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_providers');
    }
};

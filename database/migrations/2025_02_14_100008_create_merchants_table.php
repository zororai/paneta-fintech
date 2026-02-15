<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('business_name');
            $table->string('business_registration_number')->nullable();
            $table->string('business_type')->nullable();
            $table->string('country', 2)->default('ZA');
            $table->enum('kyb_status', ['pending', 'under_review', 'verified', 'rejected'])->default('pending');
            $table->string('default_currency', 3)->default('ZAR');
            $table->foreignId('settlement_account_id')->nullable()->constrained('linked_accounts');
            $table->decimal('transaction_fee_percentage', 5, 4)->default(0.99);
            $table->boolean('is_active')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['kyb_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};

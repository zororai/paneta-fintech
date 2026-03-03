<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_provider_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('trading_name');
            $table->string('trading_volume');
            $table->string('daily_limit');
            $table->string('licenses_path')->nullable();
            $table->string('certificates_path')->nullable();
            $table->date('license_validity');
            $table->string('email');
            $table->string('phone');
            $table->text('physical_address');
            $table->string('country_of_origin', 2);
            $table->text('settlement_accounts');
            $table->text('key_services');
            $table->date('member_since');
            $table->string('trading_as');
            $table->string('processing_fee');
            $table->string('tax_clearance_path')->nullable();
            $table->string('tax_id');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_provider_registrations');
    }
};

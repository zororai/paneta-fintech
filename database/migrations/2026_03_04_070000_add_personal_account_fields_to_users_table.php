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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_type', ['personal', 'business'])->default('personal')->after('role');
            $table->string('country_code', 6)->nullable()->after('country');
            $table->string('phone', 32)->nullable()->after('country_code');
            $table->string('country_of_origin', 2)->nullable()->after('phone');
            $table->string('city')->nullable()->after('country_of_origin');
            $table->string('address')->nullable()->after('city');
            $table->string('profile_picture_path')->nullable()->after('address');
            $table->string('pin_hash')->nullable()->after('profile_picture_path');

            // Business-specific fields
            $table->string('company_name')->nullable()->after('pin_hash');
            $table->string('business_phone', 32)->nullable()->after('company_name');
            $table->string('company_type')->nullable()->after('business_phone');
            $table->string('business_sector')->nullable()->after('company_type');
            $table->text('services_offered')->nullable()->after('business_sector');
            $table->string('registration_number')->nullable()->after('services_offered');
            $table->string('physical_address')->nullable()->after('registration_number');
            $table->string('website')->nullable()->after('physical_address');
            $table->string('tax_id')->nullable()->after('website');
            $table->string('business_email')->nullable()->after('tax_id');
            $table->string('company_logo_path')->nullable()->after('business_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'account_type',
                'country_code',
                'phone',
                'country_of_origin',
                'city',
                'address',
                'profile_picture_path',
                'pin_hash',
                'company_name',
                'business_phone',
                'company_type',
                'business_sector',
                'services_offered',
                'registration_number',
                'physical_address',
                'website',
                'tax_id',
                'business_email',
                'company_logo_path',
            ]);
        });
    }
};

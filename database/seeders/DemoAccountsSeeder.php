<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountsSeeder extends Seeder
{
    /**
     * Create demo accounts for Personal and Business users.
     */
    public function run(): void
    {
        // Personal Demo Account
        User::updateOrCreate(
            ['email' => 'personal@demo.com'],
            [
                'name' => 'John Personal',
                'password' => Hash::make('1234'),
                'pin_hash' => Hash::make('1234'),
                'account_type' => 'personal',
                'country_code' => '+263',
                'phone' => '771234567',
                'date_of_birth' => '1990-05-15',
                'country_of_origin' => 'ZW',
                'city' => 'Harare',
                'address' => '123 Demo Street',
            ]
        );

        $this->command->info('Personal demo account created: personal@demo.com / PIN: 1234');

        // Business Demo Account
        User::updateOrCreate(
            ['email' => 'business@demo.com'],
            [
                'name' => 'Acme Corporation',
                'password' => Hash::make('1234'),
                'pin_hash' => Hash::make('1234'),
                'account_type' => 'business',
                'country_code' => '+263',
                'phone' => '242123456',
                'company_name' => 'Acme Corporation',
                'business_phone' => '242123456',
                'company_type' => 'corporation',
                'business_sector' => 'technology',
                'registration_number' => 'REG-2024-001234',
                'physical_address' => '456 Business Park, Harare',
                'business_email' => 'business@demo.com',
                'services_offered' => 'Software Development, IT Consulting',
            ]
        );

        $this->command->info('Business demo account created: business@demo.com / PIN: 1234');
    }
}

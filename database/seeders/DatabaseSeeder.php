<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed institutions first
        $this->call(InstitutionSeeder::class);

        // Seed FX providers
        $this->call(FxProviderSeeder::class);

        // Seed subscription plans
        $this->call(SubscriptionPlanSeeder::class);

        // Create test user with verified KYC
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'kyc_status' => 'verified',
            'risk_tier' => 'low',
            'role' => 'user',
        ]);

        // Create admin user
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'kyc_status' => 'verified',
            'risk_tier' => 'low',
            'role' => 'admin',
        ]);

        // Create regulator user (read-only access)
        User::factory()->create([
            'name' => 'Regulator User',
            'email' => 'regulator@example.com',
            'password' => Hash::make('password'),
            'kyc_status' => 'verified',
            'risk_tier' => 'low',
            'role' => 'regulator',
        ]);
    }
}

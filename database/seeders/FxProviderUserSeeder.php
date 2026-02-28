<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\FxProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FxProviderUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create Global FX Solutions provider and user
        $globalFxProvider = FxProvider::updateOrCreate(
            ['code' => 'globalfx'],
            [
                'name' => 'Global FX Solutions',
                'code' => 'globalfx',
                'country' => 'US',
                'is_active' => true,
                'risk_score' => 10,
                'default_spread_percentage' => 0.25,
                'supported_pairs' => ['USD/EUR', 'USD/GBP', 'USD/JPY', 'USD/ZWL', 'USD/ZAR', 'EUR/GBP', 'EUR/JPY', 'EUR/ZAR', 'GBP/JPY', 'GBP/ZAR'],
            ]
        );

        User::updateOrCreate(
            ['email' => 'provider@globalfx.com'],
            [
                'name' => 'Global FX Solutions',
                'email' => 'provider@globalfx.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'fx_provider',
                'kyc_status' => 'verified',
                'risk_tier' => 'low',
                'fx_provider_id' => $globalFxProvider->id,
            ]
        );

        // Create Zimbabwe Currency Exchange (ZimFX) provider and user
        $zimFxProvider = FxProvider::updateOrCreate(
            ['code' => 'zimfx'],
            [
                'name' => 'Zimbabwe Currency Exchange',
                'code' => 'zimfx',
                'country' => 'ZW',
                'is_active' => true,
                'risk_score' => 25,
                'default_spread_percentage' => 0.40,
                'supported_pairs' => ['ZWL/USD', 'ZWL/ZAR', 'ZWL/GBP', 'USD/ZAR', 'USD/GBP', 'ZAR/GBP'],
            ]
        );

        User::updateOrCreate(
            ['email' => 'provider@zimfx.co.zw'],
            [
                'name' => 'Zimbabwe Currency Exchange',
                'email' => 'provider@zimfx.co.zw',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'fx_provider',
                'kyc_status' => 'verified',
                'risk_tier' => 'medium',
                'fx_provider_id' => $zimFxProvider->id,
            ]
        );

        $this->command->info('FX Provider test accounts created successfully!');
        $this->command->info('Account 1: provider@globalfx.com / password');
        $this->command->info('Account 2: provider@zimfx.co.zw / password');
    }
}

<?php

namespace Database\Seeders;

use App\Models\FxProvider;
use Illuminate\Database\Seeder;

class FxProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'Global FX Partners',
                'code' => 'gfx',
                'country' => 'US',
                'is_active' => true,
                'risk_score' => 20,
                'default_spread_percentage' => 0.35,
                'supported_pairs' => ['USD/EUR', 'USD/GBP', 'EUR/GBP', 'USD/ZAR', 'EUR/ZAR'],
            ],
            [
                'name' => 'African Exchange Hub',
                'code' => 'aeh',
                'country' => 'ZA',
                'is_active' => true,
                'risk_score' => 35,
                'default_spread_percentage' => 0.45,
                'supported_pairs' => ['USD/ZAR', 'EUR/ZAR', 'GBP/ZAR', 'USD/NGN', 'USD/KES', 'ZAR/NGN'],
            ],
            [
                'name' => 'EuroFX Direct',
                'code' => 'efx',
                'country' => 'DE',
                'is_active' => true,
                'risk_score' => 15,
                'default_spread_percentage' => 0.25,
                'supported_pairs' => ['EUR/USD', 'EUR/GBP', 'EUR/CHF', 'EUR/JPY'],
            ],
            [
                'name' => 'Naira Connect',
                'code' => 'ngx',
                'country' => 'NG',
                'is_active' => true,
                'risk_score' => 55,
                'default_spread_percentage' => 0.75,
                'supported_pairs' => ['USD/NGN', 'GBP/NGN', 'EUR/NGN', 'ZAR/NGN'],
            ],
            [
                'name' => 'East Africa FX',
                'code' => 'eafx',
                'country' => 'KE',
                'is_active' => true,
                'risk_score' => 45,
                'default_spread_percentage' => 0.55,
                'supported_pairs' => ['USD/KES', 'EUR/KES', 'GBP/KES', 'ZAR/KES'],
            ],
            [
                'name' => 'Swiss Forex',
                'code' => 'sfx',
                'country' => 'CH',
                'is_active' => true,
                'risk_score' => 10,
                'default_spread_percentage' => 0.20,
                'supported_pairs' => ['USD/CHF', 'EUR/CHF', 'GBP/CHF', 'CHF/JPY'],
            ],
        ];

        foreach ($providers as $provider) {
            FxProvider::updateOrCreate(
                ['code' => $provider['code']],
                $provider
            );
        }
    }
}

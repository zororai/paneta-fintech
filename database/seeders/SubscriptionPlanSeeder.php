<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'code' => 'basic',
                'description' => 'Essential features for personal use',
                'monthly_price' => 9.99,
                'annual_price' => 99.99,
                'currency' => 'USD',
                'tier' => 1,
                'features' => [
                    'cross_border_enabled',
                    'multi_currency',
                    'email_support',
                ],
                'limits' => [
                    'max_linked_accounts' => 5,
                    'max_daily_transactions' => 20,
                    'max_transaction_amount' => 10000,
                ],
            ],
            [
                'name' => 'Premium',
                'code' => 'premium',
                'description' => 'Advanced features for power users',
                'monthly_price' => 29.99,
                'annual_price' => 299.99,
                'currency' => 'USD',
                'tier' => 2,
                'features' => [
                    'cross_border_enabled',
                    'multi_currency',
                    'p2p_fx_enabled',
                    'wealth_analytics_enabled',
                    'priority_support',
                ],
                'limits' => [
                    'max_linked_accounts' => 20,
                    'max_daily_transactions' => 100,
                    'max_transaction_amount' => 50000,
                ],
            ],
            [
                'name' => 'Enterprise',
                'code' => 'enterprise',
                'description' => 'Unlimited access for businesses',
                'monthly_price' => 99.99,
                'annual_price' => 999.99,
                'currency' => 'USD',
                'tier' => 3,
                'features' => [
                    'cross_border_enabled',
                    'multi_currency',
                    'p2p_fx_enabled',
                    'wealth_analytics_enabled',
                    'priority_support',
                    'dedicated_account_manager',
                    'api_access',
                    'custom_integrations',
                ],
                'limits' => [
                    'max_linked_accounts' => -1,
                    'max_daily_transactions' => -1,
                    'max_transaction_amount' => -1,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['code' => $plan['code']],
                $plan
            );
        }
    }
}

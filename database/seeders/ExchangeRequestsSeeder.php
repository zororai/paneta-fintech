<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\FxProvider;
use App\Models\ExchangeRequest;
use App\Models\LinkedAccount;
use Illuminate\Database\Seeder;

class ExchangeRequestsSeeder extends Seeder
{
    /**
     * Create demo exchange requests for FX Providers.
     */
    public function run(): void
    {
        // Get FX Providers
        $globalFx = FxProvider::where('name', 'Global FX Solutions')->first();
        $zimFx = FxProvider::where('name', 'Zimbabwe Currency Exchange')->first();

        if (!$globalFx || !$zimFx) {
            $this->command->error('FX Providers not found. Please run FX Provider seeders first.');
            return;
        }

        // Get demo users
        $personalUser = User::where('email', 'personal@demo.com')->first();
        $businessUser = User::where('email', 'business@demo.com')->first();

        if (!$personalUser || !$businessUser) {
            $this->command->error('Demo users not found. Please run DemoAccountsSeeder first.');
            return;
        }

        // Get linked accounts for demo users (if they exist)
        $personalAccount = LinkedAccount::where('user_id', $personalUser->id)->first();
        $businessAccount = LinkedAccount::where('user_id', $businessUser->id)->first();

        // Exchange Request 1: Pending - USD to EUR
        ExchangeRequest::create([
            'user_id' => $personalUser->id,
            'fx_provider_id' => $globalFx->id,
            'user_source_account_id' => $personalAccount?->id,
            'user_destination_account_id' => $personalAccount?->id,
            'sell_currency' => 'USD',
            'buy_currency' => 'EUR',
            'sell_amount' => 1000.00,
            'buy_amount' => 850.00,
            'exchange_rate' => 0.85,
            'provider_fee' => 2.00,
            'platform_fee' => 0.99,
            'total_fees' => 2.99,
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        // Exchange Request 2: Pending - GBP to USD
        ExchangeRequest::create([
            'user_id' => $businessUser->id,
            'fx_provider_id' => $globalFx->id,
            'user_source_account_id' => $businessAccount?->id,
            'user_destination_account_id' => $businessAccount?->id,
            'sell_currency' => 'GBP',
            'buy_currency' => 'USD',
            'sell_amount' => 500.00,
            'buy_amount' => 625.00,
            'exchange_rate' => 1.25,
            'provider_fee' => 1.50,
            'platform_fee' => 0.99,
            'total_fees' => 2.49,
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        // Exchange Request 3: Accepted - Awaiting User Payment
        ExchangeRequest::create([
            'user_id' => $personalUser->id,
            'fx_provider_id' => $zimFx->id,
            'user_source_account_id' => $personalAccount?->id,
            'user_destination_account_id' => $personalAccount?->id,
            'sell_currency' => 'USD',
            'buy_currency' => 'ZWL',
            'sell_amount' => 200.00,
            'buy_amount' => 64400.00,
            'exchange_rate' => 322.00,
            'provider_fee' => 3.00,
            'platform_fee' => 0.99,
            'total_fees' => 3.99,
            'status' => 'accepted',
            'accepted_at' => now()->subHours(2),
            'expires_at' => now()->addHours(22),
        ]);

        // Exchange Request 4: User Paid - Awaiting Provider Payment
        ExchangeRequest::create([
            'user_id' => $businessUser->id,
            'fx_provider_id' => $globalFx->id,
            'user_source_account_id' => $businessAccount?->id,
            'user_destination_account_id' => $businessAccount?->id,
            'sell_currency' => 'EUR',
            'buy_currency' => 'USD',
            'sell_amount' => 750.00,
            'buy_amount' => 825.00,
            'exchange_rate' => 1.10,
            'provider_fee' => 2.50,
            'platform_fee' => 0.99,
            'total_fees' => 3.49,
            'status' => 'user_paid',
            'accepted_at' => now()->subHours(5),
            'user_paid_at' => now()->subHours(1),
            'expires_at' => now()->addHours(19),
        ]);

        // Exchange Request 5: Completed
        ExchangeRequest::create([
            'user_id' => $personalUser->id,
            'fx_provider_id' => $zimFx->id,
            'user_source_account_id' => $personalAccount?->id,
            'user_destination_account_id' => $personalAccount?->id,
            'sell_currency' => 'ZAR',
            'buy_currency' => 'USD',
            'sell_amount' => 1000.00,
            'buy_amount' => 55.00,
            'exchange_rate' => 0.055,
            'provider_fee' => 1.00,
            'platform_fee' => 0.99,
            'total_fees' => 1.99,
            'status' => 'completed',
            'accepted_at' => now()->subDays(1),
            'user_paid_at' => now()->subDays(1)->addHours(2),
            'provider_paid_at' => now()->subDays(1)->addHours(3),
            'completed_at' => now()->subDays(1)->addHours(3),
        ]);

        // Exchange Request 6: Rejected
        ExchangeRequest::create([
            'user_id' => $businessUser->id,
            'fx_provider_id' => $globalFx->id,
            'user_source_account_id' => $businessAccount?->id,
            'user_destination_account_id' => $businessAccount?->id,
            'sell_currency' => 'JPY',
            'buy_currency' => 'USD',
            'sell_amount' => 100000.00,
            'buy_amount' => 680.00,
            'exchange_rate' => 0.0068,
            'provider_fee' => 5.00,
            'platform_fee' => 0.99,
            'total_fees' => 5.99,
            'status' => 'rejected',
            'rejected_at' => now()->subHours(3),
            'rejection_reason' => 'Insufficient liquidity for this currency pair at the moment.',
        ]);

        $this->command->info('Created 6 demo exchange requests for FX Providers');
        $this->command->info('- 2 Pending (need review)');
        $this->command->info('- 1 Accepted (awaiting user payment)');
        $this->command->info('- 1 User Paid (awaiting provider payment - ACTION REQUIRED)');
        $this->command->info('- 1 Completed');
        $this->command->info('- 1 Rejected');
    }
}

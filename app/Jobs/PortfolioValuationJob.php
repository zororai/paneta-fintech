<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\InvestmentAccount;
use App\Models\WealthPortfolio;
use App\Services\AssetNormalisationEngine;
use App\Services\WealthAnalyticsEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PortfolioValuationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;
    public array $backoff = [30, 60, 120];

    protected ?int $userId;

    public function __construct(?int $userId = null)
    {
        $this->userId = $userId;
        $this->onQueue('analytics');
    }

    public function handle(AssetNormalisationEngine $normaliser, WealthAnalyticsEngine $analytics): void
    {
        if ($this->userId) {
            $this->processUser(User::findOrFail($this->userId), $normaliser, $analytics);
        } else {
            $this->processAllUsers($normaliser, $analytics);
        }
    }

    protected function processUser(User $user, AssetNormalisationEngine $normaliser, WealthAnalyticsEngine $analytics): void
    {
        Log::info('Processing portfolio valuation', ['user_id' => $user->id]);

        try {
            // Get all investment accounts for the user
            $investmentAccounts = InvestmentAccount::forUser($user)->get();

            $totalValue = 0;
            $totalUnrealizedGainLoss = 0;
            $totalDayChange = 0;
            $allHoldings = [];

            foreach ($investmentAccounts as $account) {
                // Normalize and enrich holdings
                $holdings = $account->holdings ?? [];
                if (!empty($holdings)) {
                    $normalizedHoldings = $normaliser->normalizeHoldings($holdings, $account->provider_name);
                    $enrichedHoldings = $normaliser->enrichWithMarketData($normalizedHoldings);
                    
                    // Update account values
                    $accountValue = array_sum(array_column($enrichedHoldings, 'market_value'));
                    $accountGainLoss = array_sum(array_column($enrichedHoldings, 'unrealized_gain_loss'));
                    $accountDayChange = array_sum(array_column($enrichedHoldings, 'day_change'));

                    $account->update([
                        'invested_value' => $accountValue,
                        'unrealized_gain_loss' => $accountGainLoss,
                        'day_change' => $accountDayChange,
                        'total_value' => $accountValue + $account->cash_balance,
                        'last_synced_at' => now(),
                    ]);

                    $totalValue += $accountValue + $account->cash_balance;
                    $totalUnrealizedGainLoss += $accountGainLoss;
                    $totalDayChange += $accountDayChange;
                    $allHoldings = array_merge($allHoldings, $enrichedHoldings);
                }
            }

            // Consolidate duplicate holdings across accounts
            $consolidatedHoldings = $normaliser->consolidateHoldings($allHoldings);
            
            // Calculate portfolio allocation
            $allocation = $normaliser->calculatePortfolioAllocation($consolidatedHoldings);

            // Update or create wealth portfolio
            $analytics->calculatePortfolio($user);

            Log::info('Portfolio valuation completed', [
                'user_id' => $user->id,
                'total_value' => $totalValue,
                'holdings_count' => count($consolidatedHoldings),
            ]);

        } catch (\Exception $e) {
            Log::error('Portfolio valuation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function processAllUsers(AssetNormalisationEngine $normaliser, WealthAnalyticsEngine $analytics): void
    {
        $users = User::whereHas('investmentAccounts')->get();

        Log::info('Starting bulk portfolio valuation', ['user_count' => $users->count()]);

        foreach ($users as $user) {
            try {
                $this->processUser($user, $normaliser, $analytics);
            } catch (\Exception $e) {
                Log::error('Portfolio valuation failed for user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other users
            }
        }

        Log::info('Bulk portfolio valuation completed');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PortfolioValuationJob failed', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}

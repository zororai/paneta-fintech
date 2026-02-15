<?php

namespace App\Services;

use App\Models\AggregatedAccount;
use App\Models\User;
use App\Models\WealthPortfolio;
use Illuminate\Support\Collection;

class WealthAnalyticsEngine
{
    public function __construct(
        protected RiskEngine $riskEngine,
        protected AggregationEngine $aggregationEngine
    ) {}

    public function calculatePortfolio(User $user): WealthPortfolio
    {
        $accounts = AggregatedAccount::forUser($user->id)->active()->get();
        
        $portfolio = WealthPortfolio::firstOrCreate(
            ['user_id' => $user->id],
            ['base_currency' => 'USD']
        );

        $totalValue = $this->calculateTotalValue($accounts, $portfolio->base_currency);
        $currencyAllocation = $this->calculateCurrencyAllocation($accounts, $totalValue);
        $assetAllocation = $this->calculateAssetAllocation($accounts);
        $riskScore = $this->riskEngine->calculatePortfolioRisk($currencyAllocation);

        $portfolio->updateCalculation([
            'total_value' => $totalValue,
            'currency_allocation' => $currencyAllocation,
            'asset_allocation' => $assetAllocation,
            'risk_score' => $riskScore,
        ]);

        return $portfolio;
    }

    protected function calculateTotalValue(Collection $accounts, string $baseCurrency): float
    {
        $total = 0;

        foreach ($accounts as $account) {
            $converted = $this->convertToBaseCurrency(
                $account->available_balance,
                $account->currency,
                $baseCurrency
            );
            $total += $converted;
        }

        return round($total, 2);
    }

    protected function calculateCurrencyAllocation(Collection $accounts, float $totalValue): array
    {
        if ($totalValue <= 0) {
            return [];
        }

        $allocation = [];

        foreach ($accounts as $account) {
            $currency = $account->currency;
            
            if (!isset($allocation[$currency])) {
                $allocation[$currency] = 0;
            }
            
            $allocation[$currency] += $account->available_balance;
        }

        foreach ($allocation as $currency => $value) {
            $convertedValue = $this->convertToBaseCurrency($value, $currency, 'USD');
            $allocation[$currency] = round(($convertedValue / $totalValue) * 100, 2);
        }

        arsort($allocation);
        return $allocation;
    }

    protected function calculateAssetAllocation(Collection $accounts): array
    {
        $byType = [];

        foreach ($accounts as $account) {
            $type = $account->institution->type ?? 'other';
            
            if (!isset($byType[$type])) {
                $byType[$type] = 0;
            }
            
            $byType[$type] += $account->available_balance;
        }

        return $byType;
    }

    protected function convertToBaseCurrency(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $rates = [
            'USD' => 1.0,
            'EUR' => 1.08,
            'GBP' => 1.26,
            'ZAR' => 0.053,
            'NGN' => 0.00065,
        ];

        $fromRate = $rates[$fromCurrency] ?? 1.0;
        $toRate = $rates[$toCurrency] ?? 1.0;

        return $amount * ($fromRate / $toRate);
    }

    public function getPortfolioSummary(User $user): array
    {
        $portfolio = $this->calculatePortfolio($user);

        return [
            'total_value' => $portfolio->total_value,
            'base_currency' => $portfolio->base_currency,
            'currency_allocation' => $portfolio->currency_allocation,
            'asset_allocation' => $portfolio->asset_allocation,
            'risk_score' => $portfolio->risk_score,
            'risk_level' => $portfolio->getRiskLevel(),
            'diversification_score' => $portfolio->getDiversificationScore(),
            'last_calculated' => $portfolio->last_calculated_at,
        ];
    }

    public function getHistoricalPerformance(User $user, int $days = 30): array
    {
        return [
            'period_days' => $days,
            'start_value' => 0,
            'end_value' => 0,
            'absolute_change' => 0,
            'percentage_change' => 0,
            'data_points' => [],
        ];
    }

    public function getCurrencyExposure(User $user): array
    {
        $portfolio = WealthPortfolio::where('user_id', $user->id)->first();
        
        if (!$portfolio || !$portfolio->currency_allocation) {
            return [];
        }

        return collect($portfolio->currency_allocation)->map(function ($percentage, $currency) {
            return [
                'currency' => $currency,
                'percentage' => $percentage,
                'risk_rating' => $this->getCurrencyRiskRating($currency),
            ];
        })->values()->toArray();
    }

    protected function getCurrencyRiskRating(string $currency): string
    {
        $lowRisk = ['USD', 'EUR', 'GBP', 'CHF', 'JPY'];
        $mediumRisk = ['AUD', 'CAD', 'SGD', 'HKD'];

        if (in_array($currency, $lowRisk)) {
            return 'low';
        }
        if (in_array($currency, $mediumRisk)) {
            return 'medium';
        }
        return 'high';
    }
}

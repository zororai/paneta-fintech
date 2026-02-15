<?php

namespace App\Services;

use App\Models\WealthPortfolio;

class ScenarioEngine
{
    public function __construct(
        protected RiskEngine $riskEngine
    ) {}

    public function runScenario(WealthPortfolio $portfolio, string $scenarioType): ScenarioResult
    {
        $scenarios = $this->getScenarioDefinitions();

        if (!isset($scenarios[$scenarioType])) {
            return new ScenarioResult(
                success: false,
                error: 'Unknown scenario type'
            );
        }

        $scenario = $scenarios[$scenarioType];
        $impacts = $this->calculateImpacts($portfolio, $scenario['shocks']);
        $newValue = $this->applyShocks($portfolio->total_value, $portfolio->currency_allocation ?? [], $scenario['shocks']);

        return new ScenarioResult(
            success: true,
            scenarioName: $scenario['name'],
            description: $scenario['description'],
            originalValue: $portfolio->total_value,
            projectedValue: $newValue,
            absoluteChange: $newValue - $portfolio->total_value,
            percentageChange: $portfolio->total_value > 0 
                ? round((($newValue - $portfolio->total_value) / $portfolio->total_value) * 100, 2) 
                : 0,
            impacts: $impacts
        );
    }

    protected function getScenarioDefinitions(): array
    {
        return [
            'usd_strength' => [
                'name' => 'USD Strength',
                'description' => 'US Dollar appreciates 10% against emerging market currencies',
                'shocks' => [
                    'USD' => 0,
                    'EUR' => -5,
                    'GBP' => -5,
                    'ZAR' => -15,
                    'NGN' => -20,
                    'KES' => -15,
                ],
            ],
            'emerging_market_crisis' => [
                'name' => 'Emerging Market Crisis',
                'description' => 'Sharp depreciation in emerging market currencies',
                'shocks' => [
                    'USD' => 5,
                    'EUR' => 0,
                    'GBP' => -5,
                    'ZAR' => -30,
                    'NGN' => -40,
                    'KES' => -25,
                    'GHS' => -35,
                ],
            ],
            'global_recession' => [
                'name' => 'Global Recession',
                'description' => 'Broad market decline affecting all currencies',
                'shocks' => [
                    'USD' => -5,
                    'EUR' => -8,
                    'GBP' => -10,
                    'ZAR' => -20,
                    'NGN' => -25,
                ],
            ],
            'inflation_surge' => [
                'name' => 'Inflation Surge',
                'description' => 'High inflation erodes currency values',
                'shocks' => [
                    'USD' => -3,
                    'EUR' => -5,
                    'GBP' => -5,
                    'ZAR' => -15,
                    'NGN' => -20,
                ],
            ],
            'best_case' => [
                'name' => 'Best Case',
                'description' => 'Optimistic scenario with broad market gains',
                'shocks' => [
                    'USD' => 2,
                    'EUR' => 5,
                    'GBP' => 5,
                    'ZAR' => 10,
                    'NGN' => 8,
                ],
            ],
        ];
    }

    protected function calculateImpacts(WealthPortfolio $portfolio, array $shocks): array
    {
        $impacts = [];
        $allocation = $portfolio->currency_allocation ?? [];

        foreach ($allocation as $currency => $percentage) {
            $shock = $shocks[$currency] ?? 0;
            $valueInCurrency = ($portfolio->total_value * $percentage / 100);
            $impact = $valueInCurrency * ($shock / 100);

            $impacts[$currency] = [
                'allocation_percentage' => $percentage,
                'shock_percentage' => $shock,
                'value_impact' => round($impact, 2),
            ];
        }

        return $impacts;
    }

    protected function applyShocks(float $totalValue, array $allocation, array $shocks): float
    {
        $newValue = 0;

        foreach ($allocation as $currency => $percentage) {
            $valueInCurrency = $totalValue * ($percentage / 100);
            $shock = $shocks[$currency] ?? 0;
            $adjustedValue = $valueInCurrency * (1 + ($shock / 100));
            $newValue += $adjustedValue;
        }

        return round($newValue, 2);
    }

    public function runMultipleScenarios(WealthPortfolio $portfolio): array
    {
        $scenarios = ['usd_strength', 'emerging_market_crisis', 'global_recession', 'inflation_surge', 'best_case'];
        $results = [];

        foreach ($scenarios as $scenarioType) {
            $result = $this->runScenario($portfolio, $scenarioType);
            if ($result->success) {
                $results[] = [
                    'scenario' => $scenarioType,
                    'name' => $result->scenarioName,
                    'projected_value' => $result->projectedValue,
                    'percentage_change' => $result->percentageChange,
                ];
            }
        }

        usort($results, fn($a, $b) => $b['percentage_change'] <=> $a['percentage_change']);

        return $results;
    }

    public function getStressTestSummary(WealthPortfolio $portfolio): array
    {
        $results = $this->runMultipleScenarios($portfolio);

        $worstCase = end($results);
        $bestCase = reset($results);

        return [
            'current_value' => $portfolio->total_value,
            'best_case' => $bestCase,
            'worst_case' => $worstCase,
            'max_potential_loss' => $worstCase ? abs($worstCase['percentage_change']) : 0,
            'max_potential_gain' => $bestCase ? $bestCase['percentage_change'] : 0,
            'all_scenarios' => $results,
        ];
    }
}

class ScenarioResult
{
    public function __construct(
        public bool $success,
        public ?string $error = null,
        public string $scenarioName = '',
        public string $description = '',
        public float $originalValue = 0,
        public float $projectedValue = 0,
        public float $absoluteChange = 0,
        public float $percentageChange = 0,
        public array $impacts = []
    ) {}
}

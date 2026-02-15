<?php

namespace App\Services;

class RiskEngine
{
    const CURRENCY_VOLATILITY = [
        'USD' => 5,
        'EUR' => 8,
        'GBP' => 12,
        'CHF' => 6,
        'JPY' => 10,
        'AUD' => 15,
        'CAD' => 12,
        'ZAR' => 35,
        'NGN' => 45,
        'KES' => 30,
        'GHS' => 40,
    ];

    public function calculatePortfolioRisk(array $currencyAllocation): float
    {
        if (empty($currencyAllocation)) {
            return 0;
        }

        $weightedRisk = 0;
        $concentrationPenalty = $this->calculateConcentrationRisk($currencyAllocation);

        foreach ($currencyAllocation as $currency => $percentage) {
            $volatility = self::CURRENCY_VOLATILITY[$currency] ?? 50;
            $weightedRisk += ($percentage / 100) * $volatility;
        }

        $totalRisk = $weightedRisk + $concentrationPenalty;

        return min(100, max(0, round($totalRisk, 2)));
    }

    protected function calculateConcentrationRisk(array $allocation): float
    {
        if (empty($allocation)) {
            return 0;
        }

        $maxConcentration = max($allocation);
        $numCurrencies = count($allocation);

        if ($maxConcentration > 80) {
            return 20;
        } elseif ($maxConcentration > 60) {
            return 10;
        } elseif ($numCurrencies < 3) {
            return 5;
        }

        return 0;
    }

    public function assessTransactionRisk(
        float $amount,
        string $currency,
        string $transactionType
    ): TransactionRiskAssessment {
        $currencyRisk = self::CURRENCY_VOLATILITY[$currency] ?? 50;
        
        $amountRisk = match (true) {
            $amount > 50000 => 30,
            $amount > 10000 => 20,
            $amount > 5000 => 10,
            default => 5,
        };

        $typeRisk = match ($transactionType) {
            'cross_border' => 15,
            'p2p_fx' => 20,
            'merchant' => 5,
            default => 10,
        };

        $totalRisk = ($currencyRisk * 0.4) + ($amountRisk * 0.4) + ($typeRisk * 0.2);
        $riskLevel = $this->getRiskLevel($totalRisk);

        return new TransactionRiskAssessment(
            score: round($totalRisk, 2),
            level: $riskLevel,
            factors: [
                'currency_risk' => $currencyRisk,
                'amount_risk' => $amountRisk,
                'type_risk' => $typeRisk,
            ],
            requiresReview: $totalRisk > 50
        );
    }

    public function getRiskLevel(float $score): string
    {
        return match (true) {
            $score < 20 => 'low',
            $score < 40 => 'medium',
            $score < 60 => 'elevated',
            $score < 80 => 'high',
            default => 'critical',
        };
    }

    public function calculateVaR(array $holdings, float $confidenceLevel = 0.95, int $timeHorizon = 1): float
    {
        $portfolioVolatility = 0;

        foreach ($holdings as $currency => $value) {
            $volatility = (self::CURRENCY_VOLATILITY[$currency] ?? 50) / 100;
            $portfolioVolatility += ($value * $volatility) ** 2;
        }

        $portfolioVolatility = sqrt($portfolioVolatility);
        $zScore = $this->getZScore($confidenceLevel);

        return round($portfolioVolatility * $zScore * sqrt($timeHorizon), 2);
    }

    protected function getZScore(float $confidenceLevel): float
    {
        return match (true) {
            $confidenceLevel >= 0.99 => 2.33,
            $confidenceLevel >= 0.95 => 1.65,
            $confidenceLevel >= 0.90 => 1.28,
            default => 1.0,
        };
    }

    public function suggestHedging(array $currencyAllocation): array
    {
        $suggestions = [];

        foreach ($currencyAllocation as $currency => $percentage) {
            $volatility = self::CURRENCY_VOLATILITY[$currency] ?? 50;

            if ($percentage > 30 && $volatility > 25) {
                $suggestions[] = [
                    'currency' => $currency,
                    'current_allocation' => $percentage,
                    'volatility' => $volatility,
                    'recommendation' => 'Consider reducing exposure or hedging',
                    'target_allocation' => min($percentage, 25),
                ];
            }
        }

        return $suggestions;
    }
}

class TransactionRiskAssessment
{
    public function __construct(
        public float $score,
        public string $level,
        public array $factors,
        public bool $requiresReview
    ) {}
}

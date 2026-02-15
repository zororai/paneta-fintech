<?php

namespace App\Services;

use App\Models\PaymentRail;
use App\Models\TransactionIntent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IntelligentRoutingEngine
{
    const STRATEGY_LOWEST_COST = 'lowest_cost';
    const STRATEGY_FASTEST = 'fastest';
    const STRATEGY_MOST_RELIABLE = 'most_reliable';
    const STRATEGY_BALANCED = 'balanced';
    const STRATEGY_FAILOVER = 'failover';

    protected RailDiscoveryEngine $railDiscovery;

    public function __construct(RailDiscoveryEngine $railDiscovery)
    {
        $this->railDiscovery = $railDiscovery;
    }

    public function selectOptimalRoute(
        string $sourceCurrency,
        string $destinationCurrency,
        string $sourceCountry,
        string $destinationCountry,
        float $amount,
        string $strategy = self::STRATEGY_BALANCED,
        array $excludeRails = []
    ): ?array {
        $availableRails = $this->railDiscovery->discoverAvailableRails(
            $sourceCurrency, $destinationCurrency, $sourceCountry, $destinationCountry, $amount
        );

        if (!empty($excludeRails)) {
            $availableRails = $availableRails->filter(function ($option) use ($excludeRails) {
                return !in_array($option['rail']->code, $excludeRails);
            });
        }

        if ($availableRails->isEmpty()) {
            Log::warning('No available rails for route', [
                'source_currency' => $sourceCurrency,
                'destination_currency' => $destinationCurrency,
                'source_country' => $sourceCountry,
                'destination_country' => $destinationCountry,
                'amount' => $amount,
            ]);
            return null;
        }

        $scored = $this->scoreRoutes($availableRails, $strategy);
        $selected = $scored->first();

        Log::info('Route selected', [
            'strategy' => $strategy,
            'selected_rail' => $selected['rail']->code,
            'score' => $selected['score'],
            'alternatives' => $scored->count() - 1,
        ]);

        return [
            'primary' => $this->formatRouteOption($selected),
            'alternatives' => $scored->skip(1)->take(2)->map(fn($opt) => $this->formatRouteOption($opt))->values()->toArray(),
            'strategy_used' => $strategy,
        ];
    }

    public function getFailoverRoute(
        TransactionIntent $transaction,
        PaymentRail $failedRail,
        string $failureReason
    ): ?array {
        Log::warning('Initiating failover routing', [
            'transaction_id' => $transaction->id,
            'failed_rail' => $failedRail->code,
            'failure_reason' => $failureReason,
        ]);

        // Get source account details
        $sourceAccount = $transaction->linkedAccount;
        
        return $this->selectOptimalRoute(
            $sourceAccount->currency ?? 'USD',
            $transaction->metadata['destination_currency'] ?? 'USD',
            $sourceAccount->institution->country ?? 'US',
            $transaction->metadata['destination_country'] ?? 'US',
            $transaction->amount,
            self::STRATEGY_FAILOVER,
            [$failedRail->code] // Exclude the failed rail
        );
    }

    public function buildMultiRailRoute(
        string $sourceCurrency,
        string $destinationCurrency,
        string $sourceCountry,
        string $destinationCountry,
        float $amount,
        int $maxLegs = 3
    ): ?array {
        // For cross-border transactions that may require multiple rails
        $directRoute = $this->selectOptimalRoute(
            $sourceCurrency, $destinationCurrency, $sourceCountry, $destinationCountry, $amount
        );

        if ($directRoute) {
            return [
                'type' => 'direct',
                'legs' => [$directRoute['primary']],
                'total_fee' => $directRoute['primary']['fee'],
                'estimated_minutes' => $directRoute['primary']['settlement_minutes'],
            ];
        }

        // Try to find intermediate routes
        $intermediateRoutes = $this->findIntermediateRoutes(
            $sourceCurrency, $destinationCurrency, $sourceCountry, $destinationCountry, $amount, $maxLegs
        );

        if (!empty($intermediateRoutes)) {
            return [
                'type' => 'multi_leg',
                'legs' => $intermediateRoutes,
                'total_fee' => collect($intermediateRoutes)->sum('fee'),
                'estimated_minutes' => collect($intermediateRoutes)->sum('settlement_minutes'),
            ];
        }

        return null;
    }

    public function getRoutingRecommendation(
        string $sourceCurrency,
        string $destinationCurrency,
        string $sourceCountry,
        string $destinationCountry,
        float $amount
    ): array {
        $strategies = [
            self::STRATEGY_LOWEST_COST,
            self::STRATEGY_FASTEST,
            self::STRATEGY_MOST_RELIABLE,
            self::STRATEGY_BALANCED,
        ];

        $recommendations = [];
        foreach ($strategies as $strategy) {
            $route = $this->selectOptimalRoute(
                $sourceCurrency, $destinationCurrency, $sourceCountry, $destinationCountry, $amount, $strategy
            );
            if ($route) {
                $recommendations[$strategy] = $route['primary'];
            }
        }

        $recommended = $recommendations[self::STRATEGY_BALANCED] ?? reset($recommendations);

        return [
            'recommended' => $recommended,
            'by_strategy' => $recommendations,
            'recommendation_reason' => $this->getRecommendationReason($recommended, $amount),
        ];
    }

    protected function scoreRoutes(Collection $routes, string $strategy): Collection
    {
        return $routes->map(function ($option) use ($strategy) {
            $option['score'] = $this->calculateScore($option, $strategy);
            return $option;
        })->sortByDesc('score');
    }

    protected function calculateScore(array $option, string $strategy): float
    {
        $rail = $option['rail'];
        $fee = $option['fee'];
        $settlementMinutes = $option['settlement_minutes'];
        $reliability = $option['reliability_score'];

        // Normalize values to 0-100 scale
        $feeScore = max(0, 100 - ($fee * 10)); // Lower fee = higher score
        $speedScore = max(0, 100 - ($settlementMinutes / 14.4)); // 24h = 0, instant = 100
        $reliabilityScore = $reliability;

        return match ($strategy) {
            self::STRATEGY_LOWEST_COST => ($feeScore * 0.7) + ($speedScore * 0.15) + ($reliabilityScore * 0.15),
            self::STRATEGY_FASTEST => ($feeScore * 0.15) + ($speedScore * 0.7) + ($reliabilityScore * 0.15),
            self::STRATEGY_MOST_RELIABLE => ($feeScore * 0.15) + ($speedScore * 0.15) + ($reliabilityScore * 0.7),
            self::STRATEGY_FAILOVER => ($reliabilityScore * 0.5) + ($speedScore * 0.3) + ($feeScore * 0.2),
            default => ($feeScore * 0.33) + ($speedScore * 0.33) + ($reliabilityScore * 0.34), // balanced
        };
    }

    protected function formatRouteOption(array $option): array
    {
        return [
            'rail_code' => $option['rail']->code,
            'rail_name' => $option['rail']->name,
            'provider' => $option['rail']->provider,
            'type' => $option['rail']->type,
            'fee' => $option['fee'],
            'settlement_minutes' => $option['settlement_minutes'],
            'reliability_score' => $option['reliability_score'],
            'supports_instant' => $option['supports_instant'],
            'score' => $option['score'] ?? null,
        ];
    }

    protected function findIntermediateRoutes(
        string $sourceCurrency,
        string $destinationCurrency,
        string $sourceCountry,
        string $destinationCountry,
        float $amount,
        int $maxLegs
    ): array {
        // Common intermediate currencies for routing
        $intermediates = ['USD', 'EUR', 'GBP'];
        
        foreach ($intermediates as $intermediate) {
            if ($intermediate === $sourceCurrency || $intermediate === $destinationCurrency) {
                continue;
            }

            $firstLeg = $this->selectOptimalRoute(
                $sourceCurrency, $intermediate, $sourceCountry, 'US', $amount
            );

            if (!$firstLeg) {
                continue;
            }

            $secondLeg = $this->selectOptimalRoute(
                $intermediate, $destinationCurrency, 'US', $destinationCountry, $amount
            );

            if ($secondLeg) {
                return [
                    $firstLeg['primary'],
                    $secondLeg['primary'],
                ];
            }
        }

        return [];
    }

    protected function getRecommendationReason(array $route, float $amount): string
    {
        $fee = $route['fee'];
        $feePercentage = ($fee / $amount) * 100;
        $settlement = $route['settlement_minutes'];

        if ($feePercentage < 0.5 && $settlement <= 60) {
            return 'Best overall value with low fees and fast settlement';
        }
        if ($feePercentage < 0.5) {
            return 'Lowest cost option for this route';
        }
        if ($settlement <= 15) {
            return 'Fastest option with near-instant settlement';
        }
        if ($route['reliability_score'] >= 99) {
            return 'Most reliable option with excellent track record';
        }
        return 'Balanced option considering cost, speed, and reliability';
    }
}

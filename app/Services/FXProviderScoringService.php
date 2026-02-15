<?php

namespace App\Services;

use App\Models\FxProvider;
use App\Models\FxQuote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FXProviderScoringService
{
    const CACHE_TTL = 1800; // 30 minutes

    protected array $scoringWeights = [
        'rate_competitiveness' => 0.35,
        'reliability' => 0.25,
        'speed' => 0.20,
        'cost' => 0.15,
        'volume_capacity' => 0.05,
    ];

    public function scoreProvider(FxProvider $provider, string $baseCurrency, string $quoteCurrency): array
    {
        $cacheKey = "provider_score:{$provider->id}:{$baseCurrency}:{$quoteCurrency}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($provider, $baseCurrency, $quoteCurrency) {
            $scores = [
                'rate_competitiveness' => $this->calculateRateScore($provider, $baseCurrency, $quoteCurrency),
                'reliability' => $this->calculateReliabilityScore($provider),
                'speed' => $this->calculateSpeedScore($provider),
                'cost' => $this->calculateCostScore($provider),
                'volume_capacity' => $this->calculateVolumeScore($provider),
            ];

            $weightedScore = 0;
            foreach ($scores as $metric => $score) {
                $weightedScore += $score * ($this->scoringWeights[$metric] ?? 0);
            }

            return [
                'provider_id' => $provider->id,
                'provider_name' => $provider->name,
                'currency_pair' => "{$baseCurrency}/{$quoteCurrency}",
                'scores' => $scores,
                'weighted_score' => round($weightedScore, 2),
                'rank' => null, // Will be set when comparing
                'calculated_at' => now()->toIso8601String(),
            ];
        });
    }

    public function rankProviders(string $baseCurrency, string $quoteCurrency): Collection
    {
        $providers = FxProvider::where('is_active', true)->get();

        $scored = $providers->map(function ($provider) use ($baseCurrency, $quoteCurrency) {
            return $this->scoreProvider($provider, $baseCurrency, $quoteCurrency);
        })->sortByDesc('weighted_score')->values();

        // Add rankings
        return $scored->map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        });
    }

    public function getBestProvider(string $baseCurrency, string $quoteCurrency): ?array
    {
        $ranked = $this->rankProviders($baseCurrency, $quoteCurrency);
        return $ranked->first();
    }

    public function getProvidersByMetric(string $baseCurrency, string $quoteCurrency, string $metric): Collection
    {
        if (!isset($this->scoringWeights[$metric])) {
            throw new \InvalidArgumentException("Unknown metric: {$metric}");
        }

        $providers = FxProvider::where('is_active', true)->get();

        return $providers->map(function ($provider) use ($baseCurrency, $quoteCurrency, $metric) {
            $scores = $this->scoreProvider($provider, $baseCurrency, $quoteCurrency);
            return [
                'provider_id' => $provider->id,
                'provider_name' => $provider->name,
                'metric_score' => $scores['scores'][$metric] ?? 0,
            ];
        })->sortByDesc('metric_score')->values();
    }

    protected function calculateRateScore(FxProvider $provider, string $baseCurrency, string $quoteCurrency): float
    {
        // Get recent quotes from this provider
        $recentQuotes = FxQuote::where('fx_provider_id', $provider->id)
            ->where('base_currency', $baseCurrency)
            ->where('quote_currency', $quoteCurrency)
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        if ($recentQuotes->isEmpty()) {
            return 50.0; // Neutral score if no data
        }

        // Get market average
        $marketAvg = FxQuote::where('base_currency', $baseCurrency)
            ->where('quote_currency', $quoteCurrency)
            ->where('created_at', '>=', now()->subHours(24))
            ->avg('rate');

        if (!$marketAvg) {
            return 50.0;
        }

        $providerAvg = $recentQuotes->avg('rate');
        
        // Calculate how much better/worse than market
        $deviation = (($providerAvg - $marketAvg) / $marketAvg) * 100;

        // Convert to 0-100 score (positive deviation = better rate for buying)
        return min(100, max(0, 50 + ($deviation * 10)));
    }

    protected function calculateReliabilityScore(FxProvider $provider): float
    {
        // Check quote fulfillment rate over last 30 days
        $totalQuotes = FxQuote::where('fx_provider_id', $provider->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($totalQuotes === 0) {
            return 75.0; // Default for new providers
        }

        $fulfilledQuotes = FxQuote::where('fx_provider_id', $provider->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->where('status', 'executed')
            ->count();

        return ($fulfilledQuotes / $totalQuotes) * 100;
    }

    protected function calculateSpeedScore(FxProvider $provider): float
    {
        // Based on average quote response time and execution time
        $avgResponseTime = $provider->avg_response_time_ms ?? 500;
        
        // Score: <100ms = 100, 100-500ms = 80-100, 500ms-2s = 50-80, >2s = 0-50
        if ($avgResponseTime < 100) {
            return 100;
        }
        if ($avgResponseTime < 500) {
            return 80 + ((500 - $avgResponseTime) / 400 * 20);
        }
        if ($avgResponseTime < 2000) {
            return 50 + ((2000 - $avgResponseTime) / 1500 * 30);
        }
        return max(0, 50 - (($avgResponseTime - 2000) / 100));
    }

    protected function calculateCostScore(FxProvider $provider): float
    {
        // Based on spread and fees
        $spread = $provider->typical_spread_bps ?? 50; // basis points
        $feePercent = $provider->fee_percentage ?? 0.5;

        $totalCost = ($spread / 100) + $feePercent;

        // Score: <0.5% = 100, 0.5-1% = 75-100, 1-2% = 50-75, >2% = 0-50
        if ($totalCost < 0.5) {
            return 100;
        }
        if ($totalCost < 1) {
            return 75 + ((1 - $totalCost) / 0.5 * 25);
        }
        if ($totalCost < 2) {
            return 50 + ((2 - $totalCost) / 1 * 25);
        }
        return max(0, 50 - (($totalCost - 2) * 25));
    }

    protected function calculateVolumeScore(FxProvider $provider): float
    {
        // Based on daily volume capacity
        $dailyCapacity = $provider->daily_volume_limit ?? 1000000;
        $dailyUsed = $this->getProviderDailyVolume($provider);

        $utilizationPercent = ($dailyUsed / $dailyCapacity) * 100;

        // Lower utilization = more capacity = higher score
        return max(0, 100 - $utilizationPercent);
    }

    protected function getProviderDailyVolume(FxProvider $provider): float
    {
        return FxQuote::where('fx_provider_id', $provider->id)
            ->whereDate('created_at', today())
            ->where('status', 'executed')
            ->sum('amount');
    }

    public function updateProviderMetrics(FxProvider $provider): void
    {
        // Calculate and store provider performance metrics
        $last30Days = FxQuote::where('fx_provider_id', $provider->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $metrics = [
            'total_quotes' => $last30Days->count(),
            'executed_quotes' => $last30Days->where('status', 'executed')->count(),
            'total_volume' => $last30Days->where('status', 'executed')->sum('amount'),
            'avg_spread_bps' => $last30Days->avg('spread_bps') ?? 0,
            'fulfillment_rate' => $last30Days->count() > 0 
                ? ($last30Days->where('status', 'executed')->count() / $last30Days->count()) * 100 
                : 0,
        ];

        $provider->update([
            'performance_metrics' => $metrics,
            'metrics_updated_at' => now(),
        ]);

        // Clear cached scores
        Cache::forget("provider_score:{$provider->id}:*");
    }

    public function getProviderLeaderboard(): array
    {
        $providers = FxProvider::where('is_active', true)->get();

        $leaderboard = $providers->map(function ($provider) {
            // Get average score across major currency pairs
            $pairs = [
                ['USD', 'EUR'], ['USD', 'GBP'], ['EUR', 'GBP'],
                ['USD', 'ZAR'], ['GBP', 'ZAR'], ['EUR', 'ZAR'],
            ];

            $scores = [];
            foreach ($pairs as [$base, $quote]) {
                $score = $this->scoreProvider($provider, $base, $quote);
                $scores[] = $score['weighted_score'];
            }

            return [
                'provider_id' => $provider->id,
                'provider_name' => $provider->name,
                'average_score' => round(array_sum($scores) / count($scores), 2),
                'pairs_scored' => count($scores),
            ];
        })->sortByDesc('average_score')->values();

        return [
            'generated_at' => now()->toIso8601String(),
            'providers' => $leaderboard->toArray(),
        ];
    }
}

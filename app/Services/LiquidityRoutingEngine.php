<?php

namespace App\Services;

use App\Models\FxProvider;
use App\Models\FxQuote;
use Illuminate\Support\Collection;

class LiquidityRoutingEngine
{
    public function __construct(
        protected FXDiscoveryEngine $fxDiscovery,
        protected BestExecutionEngine $bestExecution
    ) {}

    public function routeOrder(
        string $baseCurrency,
        string $quoteCurrency,
        float $amount,
        string $strategy = 'best_price'
    ): RoutingResult {
        $providers = $this->getEligibleProviders($baseCurrency, $quoteCurrency);

        if ($providers->isEmpty()) {
            return new RoutingResult(
                success: false,
                error: 'No eligible providers for this currency pair'
            );
        }

        $quotes = $this->collectQuotes($providers, $baseCurrency, $quoteCurrency, $amount);

        if ($quotes->isEmpty()) {
            return new RoutingResult(
                success: false,
                error: 'No quotes available'
            );
        }

        $allocation = match ($strategy) {
            'best_price' => $this->allocateBestPrice($quotes, $amount),
            'split' => $this->allocateSplit($quotes, $amount),
            'low_risk' => $this->allocateLowRisk($quotes, $amount),
            default => $this->allocateBestPrice($quotes, $amount),
        };

        return new RoutingResult(
            success: true,
            allocation: $allocation,
            totalAmount: $amount,
            effectiveRate: $this->calculateEffectiveRate($allocation),
            strategy: $strategy
        );
    }

    protected function getEligibleProviders(string $baseCurrency, string $quoteCurrency): Collection
    {
        return FxProvider::active()
            ->orderedByRisk()
            ->get()
            ->filter(fn($p) => $p->supportsPair($baseCurrency, $quoteCurrency));
    }

    protected function collectQuotes(
        Collection $providers,
        string $baseCurrency,
        string $quoteCurrency,
        float $amount
    ): Collection {
        $quotes = collect();

        foreach ($providers as $provider) {
            $quote = FxQuote::where('fx_provider_id', $provider->id)
                ->forPair($baseCurrency, $quoteCurrency)
                ->valid()
                ->orderBy('created_at', 'desc')
                ->first();

            if ($quote) {
                $quotes->push([
                    'provider' => $provider,
                    'quote' => $quote,
                    'max_amount' => $amount,
                ]);
            }
        }

        return $quotes;
    }

    protected function allocateBestPrice(Collection $quotes, float $amount): array
    {
        $sorted = $quotes->sortByDesc(fn($q) => $q['quote']->rate);
        $best = $sorted->first();

        if (!$best) {
            return [];
        }

        return [
            [
                'provider_id' => $best['provider']->id,
                'provider_name' => $best['provider']->name,
                'quote_id' => $best['quote']->id,
                'amount' => $amount,
                'rate' => $best['quote']->rate,
                'percentage' => 100,
            ],
        ];
    }

    protected function allocateSplit(Collection $quotes, float $amount): array
    {
        $sorted = $quotes->sortByDesc(fn($q) => $q['quote']->rate)->take(3);
        $allocation = [];
        $remaining = $amount;
        $count = $sorted->count();

        foreach ($sorted as $index => $item) {
            $isLast = ($index === $count - 1);
            $portion = $isLast ? $remaining : round($amount / $count, 2);
            
            $allocation[] = [
                'provider_id' => $item['provider']->id,
                'provider_name' => $item['provider']->name,
                'quote_id' => $item['quote']->id,
                'amount' => $portion,
                'rate' => $item['quote']->rate,
                'percentage' => round(($portion / $amount) * 100, 2),
            ];
            
            $remaining -= $portion;
        }

        return $allocation;
    }

    protected function allocateLowRisk(Collection $quotes, float $amount): array
    {
        $sorted = $quotes->sortBy(fn($q) => $q['provider']->risk_score);
        $best = $sorted->first();

        if (!$best) {
            return [];
        }

        return [
            [
                'provider_id' => $best['provider']->id,
                'provider_name' => $best['provider']->name,
                'quote_id' => $best['quote']->id,
                'amount' => $amount,
                'rate' => $best['quote']->rate,
                'percentage' => 100,
                'risk_score' => $best['provider']->risk_score,
            ],
        ];
    }

    protected function calculateEffectiveRate(array $allocation): float
    {
        if (empty($allocation)) {
            return 0;
        }

        $totalAmount = array_sum(array_column($allocation, 'amount'));
        $weightedSum = 0;

        foreach ($allocation as $item) {
            $weightedSum += $item['rate'] * $item['amount'];
        }

        return $totalAmount > 0 ? round($weightedSum / $totalAmount, 8) : 0;
    }

    public function rankProviders(string $baseCurrency, string $quoteCurrency): Collection
    {
        $providers = $this->getEligibleProviders($baseCurrency, $quoteCurrency);

        return $providers->map(function ($provider) use ($baseCurrency, $quoteCurrency) {
            $latestQuote = FxQuote::where('fx_provider_id', $provider->id)
                ->forPair($baseCurrency, $quoteCurrency)
                ->valid()
                ->orderBy('created_at', 'desc')
                ->first();

            return [
                'provider' => $provider,
                'latest_rate' => $latestQuote?->rate,
                'spread' => $latestQuote?->spread_percentage,
                'risk_score' => $provider->risk_score,
                'score' => $this->calculateProviderScore($provider, $latestQuote),
            ];
        })->sortByDesc('score');
    }

    protected function calculateProviderScore(FxProvider $provider, ?FxQuote $quote): float
    {
        if (!$quote) {
            return 0;
        }

        $rateScore = $quote->rate * 10;
        $spreadPenalty = ($quote->spread_percentage ?? 0.5) * 5;
        $riskPenalty = $provider->risk_score / 10;

        return max(0, $rateScore - $spreadPenalty - $riskPenalty);
    }
}

class RoutingResult
{
    public function __construct(
        public bool $success,
        public ?string $error = null,
        public array $allocation = [],
        public float $totalAmount = 0,
        public float $effectiveRate = 0,
        public string $strategy = ''
    ) {}
}

<?php

namespace App\Services;

use App\Models\FxProvider;
use App\Models\FxQuote;
use Illuminate\Support\Collection;

class BestExecutionEngine
{
    public function findBestExecution(
        string $baseCurrency,
        string $quoteCurrency,
        float $amount
    ): BestExecutionResult {
        $quotes = FxQuote::forPair($baseCurrency, $quoteCurrency)
            ->valid()
            ->with('provider')
            ->get();

        if ($quotes->isEmpty()) {
            return new BestExecutionResult(
                success: false,
                error: 'No valid quotes available'
            );
        }

        $ranked = $this->rankQuotes($quotes, $amount);
        $best = $ranked->first();

        return new BestExecutionResult(
            success: true,
            bestQuote: $best['quote'],
            provider: $best['provider'],
            effectiveRate: $best['effective_rate'],
            estimatedOutput: $best['estimated_output'],
            allOptions: $ranked->toArray()
        );
    }

    protected function rankQuotes(Collection $quotes, float $amount): Collection
    {
        return $quotes->map(function ($quote) use ($amount) {
            $effectiveRate = $quote->ask_rate ?? $quote->rate;
            $estimatedOutput = $amount * $effectiveRate;
            
            $score = $this->calculateExecutionScore($quote, $amount);

            return [
                'quote' => $quote,
                'provider' => $quote->provider,
                'effective_rate' => $effectiveRate,
                'estimated_output' => round($estimatedOutput, 2),
                'spread' => $quote->spread_percentage,
                'score' => $score,
                'expires_in_seconds' => max(0, now()->diffInSeconds($quote->expires_at, false)),
            ];
        })->sortByDesc('score');
    }

    protected function calculateExecutionScore(FxQuote $quote, float $amount): float
    {
        $rateScore = $quote->rate * 100;
        
        $spreadPenalty = ($quote->spread_percentage ?? 0.5) * 20;
        
        $timeRemaining = max(0, now()->diffInSeconds($quote->expires_at, false));
        $timePenalty = $timeRemaining < 60 ? 10 : 0;
        
        $riskPenalty = ($quote->provider->risk_score ?? 50) / 5;

        return max(0, $rateScore - $spreadPenalty - $timePenalty - $riskPenalty);
    }

    public function compareProviders(
        string $baseCurrency,
        string $quoteCurrency,
        float $amount
    ): array {
        $quotes = FxQuote::forPair($baseCurrency, $quoteCurrency)
            ->valid()
            ->with('provider')
            ->get()
            ->groupBy('fx_provider_id');

        $comparison = [];

        foreach ($quotes as $providerId => $providerQuotes) {
            $latestQuote = $providerQuotes->sortByDesc('created_at')->first();
            $provider = $latestQuote->provider;

            $comparison[] = [
                'provider_id' => $providerId,
                'provider_name' => $provider->name,
                'rate' => $latestQuote->rate,
                'bid_rate' => $latestQuote->bid_rate,
                'ask_rate' => $latestQuote->ask_rate,
                'spread' => $latestQuote->spread_percentage,
                'risk_score' => $provider->risk_score,
                'estimated_output' => round($amount * ($latestQuote->ask_rate ?? $latestQuote->rate), 2),
                'quote_expires_at' => $latestQuote->expires_at,
            ];
        }

        usort($comparison, fn($a, $b) => $b['rate'] <=> $a['rate']);

        return $comparison;
    }

    public function validateExecution(FxQuote $quote, float $amount): array
    {
        $errors = [];

        if ($quote->isExpired()) {
            $errors[] = 'Quote has expired';
        }

        if (!$quote->provider || !$quote->provider->is_active) {
            $errors[] = 'Provider is not active';
        }

        if ($amount <= 0) {
            $errors[] = 'Invalid amount';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function lockQuote(FxQuote $quote): LockedQuote
    {
        return new LockedQuote(
            quoteId: $quote->id,
            rate: $quote->rate,
            lockedAt: now(),
            expiresAt: min($quote->expires_at, now()->addMinutes(2)),
            providerId: $quote->fx_provider_id
        );
    }
}

class BestExecutionResult
{
    public function __construct(
        public bool $success,
        public ?string $error = null,
        public ?FxQuote $bestQuote = null,
        public ?FxProvider $provider = null,
        public float $effectiveRate = 0,
        public float $estimatedOutput = 0,
        public array $allOptions = []
    ) {}
}

class LockedQuote
{
    public function __construct(
        public int $quoteId,
        public float $rate,
        public \Carbon\Carbon $lockedAt,
        public \Carbon\Carbon $expiresAt,
        public int $providerId
    ) {}

    public function isValid(): bool
    {
        return $this->expiresAt->isFuture();
    }
}

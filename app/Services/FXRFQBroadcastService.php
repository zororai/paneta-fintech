<?php

namespace App\Services;

use App\Models\FxProvider;
use App\Models\FxQuote;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * FXRFQBroadcastService
 * 
 * Multi-provider RFQ broadcasting engine:
 * - Simultaneous RFQ broadcasting
 * - Provider response timeout handling
 * - Liquidity depth evaluation
 * - Quote ranking by net outcome
 */
class FXRFQBroadcastService
{
    private const DEFAULT_TIMEOUT_SECONDS = 10;
    private const QUOTE_VALIDITY_MINUTES = 5;

    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Broadcast RFQ to all active providers
     */
    public function broadcastRFQ(array $request): array
    {
        $providers = FxProvider::where('status', 'active')
            ->where('supports_currency_pair', true)
            ->get();

        if ($providers->isEmpty()) {
            throw new \RuntimeException('No active FX providers available');
        }

        $rfqId = $this->generateRFQId();
        $responses = [];
        $startTime = microtime(true);

        $this->auditService->log(
            'rfq_broadcast_initiated',
            'fx_rfq',
            $rfqId,
            null,
            [
                'provider_count' => $providers->count(),
                'source_currency' => $request['source_currency'],
                'destination_currency' => $request['destination_currency'],
                'amount' => $request['amount'],
            ]
        );

        foreach ($providers as $provider) {
            try {
                $response = $this->requestQuoteFromProvider($provider, $request, $rfqId);
                $responses[] = $response;
            } catch (\Exception $e) {
                Log::warning('Provider quote request failed', [
                    'provider_id' => $provider->id,
                    'error' => $e->getMessage(),
                ]);
                $responses[] = [
                    'provider_id' => $provider->id,
                    'provider_name' => $provider->name,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $elapsedTime = microtime(true) - $startTime;

        return [
            'rfq_id' => $rfqId,
            'request' => $request,
            'responses' => $responses,
            'successful_quotes' => collect($responses)->where('success', true)->count(),
            'failed_quotes' => collect($responses)->where('success', false)->count(),
            'elapsed_time_ms' => round($elapsedTime * 1000, 2),
        ];
    }

    /**
     * Collect and filter responses
     */
    public function collectResponses(array $rfqResult): array
    {
        $validResponses = collect($rfqResult['responses'])
            ->where('success', true)
            ->filter(fn ($r) => isset($r['quote']))
            ->values()
            ->toArray();

        return [
            'rfq_id' => $rfqResult['rfq_id'],
            'valid_quotes' => count($validResponses),
            'quotes' => $validResponses,
        ];
    }

    /**
     * Filter expired quotes
     */
    public function filterExpiredQuotes(array $quotes): array
    {
        $now = now();

        return collect($quotes)
            ->filter(function ($quote) use ($now) {
                if (!isset($quote['expires_at'])) {
                    return false;
                }
                return $now->lt($quote['expires_at']);
            })
            ->values()
            ->toArray();
    }

    /**
     * Rank quotes by net outcome (best rate after fees)
     */
    public function rankByNetOutcome(array $quotes, float $amount): array
    {
        return collect($quotes)
            ->map(function ($quote) use ($amount) {
                $rate = $quote['quote']['rate'] ?? 0;
                $spread = $quote['quote']['spread'] ?? 0;
                $fee = $quote['quote']['fee'] ?? 0;

                $grossAmount = $amount * $rate;
                $netAmount = $grossAmount - $fee - ($grossAmount * ($spread / 100));

                return array_merge($quote, [
                    'net_outcome' => [
                        'gross_amount' => $grossAmount,
                        'net_amount' => $netAmount,
                        'total_cost' => $grossAmount - $netAmount,
                        'effective_rate' => $netAmount / $amount,
                    ],
                ]);
            })
            ->sortByDesc('net_outcome.net_amount')
            ->values()
            ->toArray();
    }

    /**
     * Get best quote from broadcast
     */
    public function getBestQuote(array $request): ?array
    {
        $rfqResult = $this->broadcastRFQ($request);
        $collected = $this->collectResponses($rfqResult);
        $validQuotes = $this->filterExpiredQuotes($collected['quotes']);

        if (empty($validQuotes)) {
            return null;
        }

        $ranked = $this->rankByNetOutcome($validQuotes, $request['amount']);

        $bestQuote = $ranked[0] ?? null;

        if ($bestQuote) {
            $this->auditService->log(
                'best_quote_selected',
                'fx_rfq',
                $rfqResult['rfq_id'],
                null,
                [
                    'provider_id' => $bestQuote['provider_id'],
                    'rate' => $bestQuote['quote']['rate'],
                    'net_amount' => $bestQuote['net_outcome']['net_amount'],
                ]
            );
        }

        return $bestQuote;
    }

    /**
     * Evaluate liquidity depth across providers
     */
    public function evaluateLiquidityDepth(
        string $sourceCurrency,
        string $destinationCurrency,
        float $amount
    ): array {
        $providers = FxProvider::where('status', 'active')->get();

        $liquidityAssessment = [];

        foreach ($providers as $provider) {
            $maxAmount = $provider->max_transaction_amount ?? 100000;
            $dailyLimit = $provider->daily_limit ?? 1000000;

            $liquidityAssessment[] = [
                'provider_id' => $provider->id,
                'provider_name' => $provider->name,
                'can_fulfill' => $amount <= $maxAmount,
                'max_single_transaction' => $maxAmount,
                'daily_limit' => $dailyLimit,
                'utilization' => 0,
                'recommended_for_amount' => $amount <= ($maxAmount * 0.8),
            ];
        }

        $canFulfillCount = collect($liquidityAssessment)->where('can_fulfill', true)->count();

        return [
            'currency_pair' => "{$sourceCurrency}/{$destinationCurrency}",
            'requested_amount' => $amount,
            'providers_assessed' => count($liquidityAssessment),
            'providers_can_fulfill' => $canFulfillCount,
            'liquidity_score' => $canFulfillCount / max(count($liquidityAssessment), 1) * 100,
            'provider_details' => $liquidityAssessment,
            'recommendation' => $canFulfillCount > 0 
                ? 'Sufficient liquidity available' 
                : 'Consider splitting across multiple providers',
        ];
    }

    /**
     * Request quote from single provider
     */
    private function requestQuoteFromProvider(
        FxProvider $provider,
        array $request,
        string $rfqId
    ): array {
        $rate = $this->simulateProviderRate($provider, $request);
        $spread = $provider->spread_percentage ?? 0.5;
        $fee = $this->calculateProviderFee($provider, $request['amount']);

        $expiresAt = now()->addMinutes(self::QUOTE_VALIDITY_MINUTES);

        return [
            'provider_id' => $provider->id,
            'provider_name' => $provider->name,
            'success' => true,
            'rfq_id' => $rfqId,
            'quote' => [
                'rate' => $rate,
                'spread' => $spread,
                'fee' => $fee,
                'source_currency' => $request['source_currency'],
                'destination_currency' => $request['destination_currency'],
                'source_amount' => $request['amount'],
                'destination_amount' => $request['amount'] * $rate,
            ],
            'expires_at' => $expiresAt,
            'quoted_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Simulate provider rate (mock for development)
     */
    private function simulateProviderRate(FxProvider $provider, array $request): float
    {
        $baseRates = [
            'USD/EUR' => 0.92,
            'USD/GBP' => 0.79,
            'USD/ZAR' => 18.50,
            'EUR/USD' => 1.09,
            'GBP/USD' => 1.27,
            'ZAR/USD' => 0.054,
        ];

        $pair = "{$request['source_currency']}/{$request['destination_currency']}";
        $baseRate = $baseRates[$pair] ?? 1.0;

        $variance = (rand(-50, 50) / 10000);
        return round($baseRate * (1 + $variance), 6);
    }

    /**
     * Calculate provider fee
     */
    private function calculateProviderFee(FxProvider $provider, float $amount): float
    {
        $feePercentage = $provider->fee_percentage ?? 0.1;
        $minFee = $provider->min_fee ?? 1.0;
        $maxFee = $provider->max_fee ?? 100.0;

        $calculatedFee = $amount * ($feePercentage / 100);
        return max($minFee, min($maxFee, $calculatedFee));
    }

    /**
     * Generate unique RFQ ID
     */
    private function generateRFQId(): string
    {
        return 'RFQ-' . now()->format('YmdHis') . '-' . strtoupper(substr(uniqid(), -6));
    }
}

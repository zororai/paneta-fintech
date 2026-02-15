<?php

namespace App\Services;

use App\Models\FxProvider;
use App\Models\FxQuote;
use Illuminate\Support\Collection;

class FXDiscoveryEngine
{
    public function __construct(
        protected FXQuoteNormaliser $quoteNormaliser
    ) {}

    public function discoverRates(
        string $baseCurrency,
        string $quoteCurrency,
        ?float $amount = null
    ): Collection {
        $providers = FxProvider::active()
            ->orderedByRisk()
            ->get()
            ->filter(fn($p) => $p->supportsPair($baseCurrency, $quoteCurrency));

        $quotes = collect();

        foreach ($providers as $provider) {
            $quote = $this->fetchQuoteFromProvider($provider, $baseCurrency, $quoteCurrency, $amount);
            if ($quote) {
                $quotes->push($quote);
            }
        }

        return $quotes->sortByDesc('rate');
    }

    public function getBestRate(
        string $baseCurrency,
        string $quoteCurrency,
        ?float $amount = null
    ): ?FxQuote {
        $quotes = $this->discoverRates($baseCurrency, $quoteCurrency, $amount);
        return $quotes->first();
    }

    public function getQuoteById(int $quoteId): ?FxQuote
    {
        return FxQuote::find($quoteId);
    }

    public function isQuoteValid(FxQuote $quote): bool
    {
        return $quote->isValid();
    }

    protected function fetchQuoteFromProvider(
        FxProvider $provider,
        string $baseCurrency,
        string $quoteCurrency,
        ?float $amount = null
    ): ?FxQuote {
        $mockRate = $this->generateMockRate($baseCurrency, $quoteCurrency);
        $spread = $provider->default_spread_percentage;

        $bidRate = $mockRate * (1 - $spread / 100);
        $askRate = $mockRate * (1 + $spread / 100);

        return FxQuote::create([
            'fx_provider_id' => $provider->id,
            'base_currency' => $baseCurrency,
            'quote_currency' => $quoteCurrency,
            'rate' => $mockRate,
            'bid_rate' => $bidRate,
            'ask_rate' => $askRate,
            'spread_percentage' => $spread,
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    protected function generateMockRate(string $baseCurrency, string $quoteCurrency): float
    {
        $baseRates = [
            'USD' => 1.0,
            'EUR' => 1.08,
            'GBP' => 1.26,
            'ZAR' => 0.053,
            'NGN' => 0.00065,
            'KES' => 0.0064,
            'GHS' => 0.064,
            'JPY' => 0.0067,
            'CHF' => 1.13,
            'AUD' => 0.64,
            'CAD' => 0.74,
        ];

        $baseUsd = $baseRates[$baseCurrency] ?? 1.0;
        $quoteUsd = $baseRates[$quoteCurrency] ?? 1.0;

        $rate = $baseUsd / $quoteUsd;

        $variance = (rand(-100, 100) / 10000);
        return round($rate * (1 + $variance), 8);
    }

    public function getSupportedPairs(): array
    {
        $currencies = ['USD', 'EUR', 'GBP', 'ZAR', 'NGN', 'KES', 'GHS'];
        $pairs = [];

        foreach ($currencies as $base) {
            foreach ($currencies as $quote) {
                if ($base !== $quote) {
                    $pairs[] = "{$base}/{$quote}";
                }
            }
        }

        return $pairs;
    }

    public function getProviderQuotes(FxProvider $provider): Collection
    {
        return FxQuote::where('fx_provider_id', $provider->id)
            ->valid()
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
    }
}

<?php

namespace App\Services;

use App\Models\FxQuote;

class FXQuoteNormaliser
{
    public function normalise(array $rawQuote, int $providerId): array
    {
        return [
            'fx_provider_id' => $providerId,
            'base_currency' => $this->normaliseCurrency($rawQuote['base'] ?? $rawQuote['base_currency'] ?? 'USD'),
            'quote_currency' => $this->normaliseCurrency($rawQuote['quote'] ?? $rawQuote['quote_currency'] ?? 'USD'),
            'rate' => $this->normaliseRate($rawQuote),
            'bid_rate' => $this->normaliseRate($rawQuote, 'bid'),
            'ask_rate' => $this->normaliseRate($rawQuote, 'ask'),
            'spread_percentage' => $this->calculateSpread($rawQuote),
            'expires_at' => $this->normaliseExpiry($rawQuote),
        ];
    }

    protected function normaliseCurrency(string $currency): string
    {
        return strtoupper(trim(substr($currency, 0, 3)));
    }

    protected function normaliseRate(array $rawQuote, ?string $type = null): float
    {
        $key = match ($type) {
            'bid' => ['bid', 'bid_rate', 'buy_rate'],
            'ask' => ['ask', 'ask_rate', 'sell_rate'],
            default => ['rate', 'mid', 'mid_rate', 'exchange_rate'],
        };

        foreach ($key as $k) {
            if (isset($rawQuote[$k])) {
                return (float) $rawQuote[$k];
            }
        }

        return $rawQuote['rate'] ?? 1.0;
    }

    protected function calculateSpread(array $rawQuote): float
    {
        if (isset($rawQuote['spread'])) {
            return (float) $rawQuote['spread'];
        }

        $bid = $this->normaliseRate($rawQuote, 'bid');
        $ask = $this->normaliseRate($rawQuote, 'ask');
        $mid = $this->normaliseRate($rawQuote);

        if ($mid > 0 && $bid > 0 && $ask > 0) {
            return round((($ask - $bid) / $mid) * 100, 4);
        }

        return 0.5;
    }

    protected function normaliseExpiry(array $rawQuote): \Carbon\Carbon
    {
        if (isset($rawQuote['expires_at'])) {
            return \Carbon\Carbon::parse($rawQuote['expires_at']);
        }

        if (isset($rawQuote['ttl_seconds'])) {
            return now()->addSeconds((int) $rawQuote['ttl_seconds']);
        }

        return now()->addMinutes(5);
    }

    public function compareQuotes(FxQuote $quote1, FxQuote $quote2): int
    {
        if ($quote1->rate === $quote2->rate) {
            return $quote1->spread_percentage <=> $quote2->spread_percentage;
        }

        return $quote2->rate <=> $quote1->rate;
    }

    public function calculateEffectiveRate(FxQuote $quote, float $amount): float
    {
        return $quote->ask_rate ?? $quote->rate;
    }

    public function calculateConvertedAmount(FxQuote $quote, float $amount): float
    {
        $effectiveRate = $this->calculateEffectiveRate($quote, $amount);
        return round($amount * $effectiveRate, 2);
    }
}

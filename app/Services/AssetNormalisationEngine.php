<?php

namespace App\Services;

use App\Models\HistoricalPrice;
use App\Models\InvestmentAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AssetNormalisationEngine
{
    protected array $assetTypeMapping = [
        'EQUITY' => 'stock',
        'STOCK' => 'stock',
        'ETF' => 'etf',
        'EXCHANGE_TRADED_FUND' => 'etf',
        'MUTUAL_FUND' => 'mutual_fund',
        'MF' => 'mutual_fund',
        'BOND' => 'bond',
        'FIXED_INCOME' => 'bond',
        'CRYPTO' => 'crypto',
        'CRYPTOCURRENCY' => 'crypto',
        'COMMODITY' => 'commodity',
        'FOREX' => 'currency',
        'FX' => 'currency',
    ];

    protected array $currencySymbols = [
        '$' => 'USD',
        '€' => 'EUR',
        '£' => 'GBP',
        '¥' => 'JPY',
        'R' => 'ZAR',
    ];

    public function normalizeHoldings(array $rawHoldings, string $provider): array
    {
        return array_map(function ($holding) use ($provider) {
            return $this->normalizeHolding($holding, $provider);
        }, $rawHoldings);
    }

    public function normalizeHolding(array $raw, string $provider): array
    {
        return [
            'symbol' => $this->normalizeSymbol($raw['symbol'] ?? $raw['ticker'] ?? ''),
            'name' => $raw['name'] ?? $raw['security_name'] ?? $raw['description'] ?? '',
            'asset_type' => $this->normalizeAssetType($raw['type'] ?? $raw['asset_type'] ?? $raw['security_type'] ?? ''),
            'quantity' => (float) ($raw['quantity'] ?? $raw['shares'] ?? $raw['units'] ?? 0),
            'cost_basis' => (float) ($raw['cost_basis'] ?? $raw['purchase_price'] ?? $raw['avg_cost'] ?? 0),
            'current_price' => (float) ($raw['current_price'] ?? $raw['price'] ?? $raw['market_price'] ?? 0),
            'market_value' => (float) ($raw['market_value'] ?? $raw['value'] ?? 0),
            'currency' => $this->normalizeCurrency($raw['currency'] ?? 'USD'),
            'unrealized_gain_loss' => $this->calculateUnrealizedGainLoss($raw),
            'day_change' => (float) ($raw['day_change'] ?? $raw['change'] ?? 0),
            'day_change_percent' => (float) ($raw['day_change_percent'] ?? $raw['change_percent'] ?? 0),
            'isin' => $raw['isin'] ?? null,
            'cusip' => $raw['cusip'] ?? null,
            'exchange' => $raw['exchange'] ?? $raw['market'] ?? null,
            'sector' => $raw['sector'] ?? null,
            'raw_provider' => $provider,
        ];
    }

    public function normalizeSymbol(string $symbol): string
    {
        // Remove exchange prefixes/suffixes
        $symbol = preg_replace('/^[A-Z]{2,4}:/', '', $symbol);
        $symbol = preg_replace('/\.[A-Z]{1,3}$/', '', $symbol);
        return strtoupper(trim($symbol));
    }

    public function normalizeAssetType(string $type): string
    {
        $upperType = strtoupper(trim($type));
        return $this->assetTypeMapping[$upperType] ?? 'other';
    }

    public function normalizeCurrency(string $currency): string
    {
        $currency = trim($currency);
        
        // Check if it's a symbol
        if (isset($this->currencySymbols[$currency])) {
            return $this->currencySymbols[$currency];
        }
        
        return strtoupper(substr($currency, 0, 3));
    }

    public function calculateUnrealizedGainLoss(array $holding): float
    {
        if (isset($holding['unrealized_gain_loss'])) {
            return (float) $holding['unrealized_gain_loss'];
        }

        $quantity = (float) ($holding['quantity'] ?? $holding['shares'] ?? 0);
        $costBasis = (float) ($holding['cost_basis'] ?? $holding['avg_cost'] ?? 0);
        $currentPrice = (float) ($holding['current_price'] ?? $holding['price'] ?? 0);

        if ($quantity === 0 || $costBasis === 0) {
            return 0;
        }

        $totalCost = $quantity * $costBasis;
        $currentValue = $quantity * $currentPrice;

        return round($currentValue - $totalCost, 2);
    }

    public function enrichWithMarketData(array $normalizedHoldings): array
    {
        return array_map(function ($holding) {
            $latestPrice = HistoricalPrice::getLatestPrice($holding['symbol']);
            
            if ($latestPrice) {
                $holding['last_market_price'] = $latestPrice->close_price;
                $holding['price_date'] = $latestPrice->price_date->format('Y-m-d');
                $holding['price_change'] = $latestPrice->getPriceChange();
                $holding['price_change_percent'] = $latestPrice->getPriceChangePercent();
            }

            return $holding;
        }, $normalizedHoldings);
    }

    public function categorizeByAssetClass(array $holdings): array
    {
        $categories = [
            'equities' => [],
            'fixed_income' => [],
            'alternatives' => [],
            'cash' => [],
        ];

        foreach ($holdings as $holding) {
            $assetType = $holding['asset_type'] ?? 'other';
            
            switch ($assetType) {
                case 'stock':
                case 'etf':
                case 'mutual_fund':
                    $categories['equities'][] = $holding;
                    break;
                case 'bond':
                    $categories['fixed_income'][] = $holding;
                    break;
                case 'crypto':
                case 'commodity':
                    $categories['alternatives'][] = $holding;
                    break;
                case 'currency':
                    $categories['cash'][] = $holding;
                    break;
                default:
                    $categories['alternatives'][] = $holding;
            }
        }

        return $categories;
    }

    public function calculatePortfolioAllocation(array $holdings): array
    {
        $totalValue = array_sum(array_column($holdings, 'market_value'));
        
        if ($totalValue <= 0) {
            return [];
        }

        $byAssetType = [];
        foreach ($holdings as $holding) {
            $type = $holding['asset_type'] ?? 'other';
            if (!isset($byAssetType[$type])) {
                $byAssetType[$type] = 0;
            }
            $byAssetType[$type] += $holding['market_value'] ?? 0;
        }

        $allocation = [];
        foreach ($byAssetType as $type => $value) {
            $allocation[$type] = [
                'value' => round($value, 2),
                'percentage' => round(($value / $totalValue) * 100, 2),
            ];
        }

        arsort($allocation);
        return $allocation;
    }

    public function detectDuplicateHoldings(array $holdings): array
    {
        $symbolCounts = [];
        $duplicates = [];

        foreach ($holdings as $index => $holding) {
            $symbol = $holding['symbol'] ?? '';
            if (!isset($symbolCounts[$symbol])) {
                $symbolCounts[$symbol] = [];
            }
            $symbolCounts[$symbol][] = $index;
        }

        foreach ($symbolCounts as $symbol => $indices) {
            if (count($indices) > 1) {
                $duplicates[$symbol] = $indices;
            }
        }

        return $duplicates;
    }

    public function consolidateHoldings(array $holdings): array
    {
        $consolidated = [];

        foreach ($holdings as $holding) {
            $symbol = $holding['symbol'] ?? '';
            
            if (!isset($consolidated[$symbol])) {
                $consolidated[$symbol] = $holding;
                continue;
            }

            // Merge quantities and recalculate
            $existing = $consolidated[$symbol];
            $totalQuantity = ($existing['quantity'] ?? 0) + ($holding['quantity'] ?? 0);
            $totalCost = (($existing['quantity'] ?? 0) * ($existing['cost_basis'] ?? 0)) +
                        (($holding['quantity'] ?? 0) * ($holding['cost_basis'] ?? 0));
            
            $consolidated[$symbol]['quantity'] = $totalQuantity;
            $consolidated[$symbol]['cost_basis'] = $totalQuantity > 0 ? $totalCost / $totalQuantity : 0;
            $consolidated[$symbol]['market_value'] = ($existing['market_value'] ?? 0) + ($holding['market_value'] ?? 0);
        }

        return array_values($consolidated);
    }
}

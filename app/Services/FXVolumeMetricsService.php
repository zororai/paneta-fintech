<?php

namespace App\Services;

use App\Models\FxQuote;
use App\Models\FxProvider;
use App\Models\CrossBorderTransactionIntent;
use App\Models\FxOffer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FXVolumeMetricsService
{
    const CACHE_TTL = 300; // 5 minutes

    public function getDailyVolume(string $date = null): array
    {
        $date = $date ?? today()->format('Y-m-d');
        $cacheKey = "fx_daily_volume:{$date}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($date) {
            $quotes = FxQuote::whereDate('created_at', $date)
                ->where('status', 'executed')
                ->get();

            $crossBorder = CrossBorderTransactionIntent::whereDate('created_at', $date)
                ->whereIn('status', ['completed', 'fx_executed'])
                ->get();

            $p2p = FxOffer::whereDate('created_at', $date)
                ->where('status', 'executed')
                ->get();

            return [
                'date' => $date,
                'total_volume' => $quotes->sum('amount') + $crossBorder->sum('source_amount') + $p2p->sum('amount'),
                'quote_volume' => $quotes->sum('amount'),
                'cross_border_volume' => $crossBorder->sum('source_amount'),
                'p2p_volume' => $p2p->sum('amount'),
                'transaction_count' => $quotes->count() + $crossBorder->count() + $p2p->count(),
                'by_currency_pair' => $this->getVolumeByCurrencyPair($quotes),
                'by_provider' => $this->getVolumeByProvider($quotes),
            ];
        });
    }

    public function getVolumeByPeriod(int $days = 30): array
    {
        $cacheKey = "fx_volume_period:{$days}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($days) {
            $startDate = now()->subDays($days);
            
            $dailyVolumes = FxQuote::where('created_at', '>=', $startDate)
                ->where('status', 'executed')
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(amount) as volume'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('AVG(rate) as avg_rate')
                )
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get();

            return [
                'period_days' => $days,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'total_volume' => $dailyVolumes->sum('volume'),
                'total_transactions' => $dailyVolumes->sum('count'),
                'average_daily_volume' => round($dailyVolumes->avg('volume') ?? 0, 2),
                'peak_volume' => $dailyVolumes->max('volume'),
                'peak_date' => $dailyVolumes->sortByDesc('volume')->first()?->date,
                'daily_breakdown' => $dailyVolumes->toArray(),
            ];
        });
    }

    public function getCurrencyPairMetrics(string $baseCurrency, string $quoteCurrency, int $days = 7): array
    {
        $cacheKey = "fx_pair_metrics:{$baseCurrency}:{$quoteCurrency}:{$days}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($baseCurrency, $quoteCurrency, $days) {
            $startDate = now()->subDays($days);

            $quotes = FxQuote::where('base_currency', $baseCurrency)
                ->where('quote_currency', $quoteCurrency)
                ->where('created_at', '>=', $startDate)
                ->where('status', 'executed')
                ->get();

            $rates = $quotes->pluck('rate');

            return [
                'currency_pair' => "{$baseCurrency}/{$quoteCurrency}",
                'period_days' => $days,
                'total_volume' => $quotes->sum('amount'),
                'transaction_count' => $quotes->count(),
                'average_rate' => round($rates->avg() ?? 0, 6),
                'min_rate' => round($rates->min() ?? 0, 6),
                'max_rate' => round($rates->max() ?? 0, 6),
                'rate_volatility' => $this->calculateVolatility($rates->toArray()),
                'average_spread_bps' => round($quotes->avg('spread_bps') ?? 0, 2),
                'hourly_distribution' => $this->getHourlyDistribution($quotes),
            ];
        });
    }

    public function getProviderVolumeShare(int $days = 30): array
    {
        $cacheKey = "fx_provider_share:{$days}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($days) {
            $startDate = now()->subDays($days);

            $providerVolumes = FxQuote::where('created_at', '>=', $startDate)
                ->where('status', 'executed')
                ->select(
                    'fx_provider_id',
                    DB::raw('SUM(amount) as volume'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('fx_provider_id')
                ->get();

            $totalVolume = $providerVolumes->sum('volume');
            $providers = FxProvider::whereIn('id', $providerVolumes->pluck('fx_provider_id'))->get()->keyBy('id');

            return [
                'period_days' => $days,
                'total_volume' => $totalVolume,
                'providers' => $providerVolumes->map(function ($pv) use ($totalVolume, $providers) {
                    return [
                        'provider_id' => $pv->fx_provider_id,
                        'provider_name' => $providers[$pv->fx_provider_id]->name ?? 'Unknown',
                        'volume' => $pv->volume,
                        'count' => $pv->count,
                        'market_share' => $totalVolume > 0 ? round(($pv->volume / $totalVolume) * 100, 2) : 0,
                    ];
                })->sortByDesc('volume')->values()->toArray(),
            ];
        });
    }

    public function getRealTimeMetrics(): array
    {
        // Not cached - real-time data
        $lastHour = now()->subHour();
        $last24Hours = now()->subHours(24);

        $hourlyQuotes = FxQuote::where('created_at', '>=', $lastHour)->get();
        $dailyQuotes = FxQuote::where('created_at', '>=', $last24Hours)->get();

        return [
            'timestamp' => now()->toIso8601String(),
            'last_hour' => [
                'volume' => $hourlyQuotes->where('status', 'executed')->sum('amount'),
                'quotes_requested' => $hourlyQuotes->count(),
                'quotes_executed' => $hourlyQuotes->where('status', 'executed')->count(),
                'quotes_expired' => $hourlyQuotes->where('status', 'expired')->count(),
                'conversion_rate' => $hourlyQuotes->count() > 0 
                    ? round(($hourlyQuotes->where('status', 'executed')->count() / $hourlyQuotes->count()) * 100, 2) 
                    : 0,
            ],
            'last_24_hours' => [
                'volume' => $dailyQuotes->where('status', 'executed')->sum('amount'),
                'quotes_requested' => $dailyQuotes->count(),
                'quotes_executed' => $dailyQuotes->where('status', 'executed')->count(),
                'average_execution_time_ms' => round($dailyQuotes->where('status', 'executed')->avg('execution_time_ms') ?? 0),
            ],
            'active_quotes' => FxQuote::where('status', 'pending')
                ->where('expires_at', '>', now())
                ->count(),
            'locked_quotes' => FxQuote::where('status', 'locked')
                ->where('lock_expires_at', '>', now())
                ->count(),
        ];
    }

    public function getCorridorAnalysis(int $days = 30): array
    {
        $cacheKey = "fx_corridor_analysis:{$days}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($days) {
            $startDate = now()->subDays($days);

            $corridors = FxQuote::where('created_at', '>=', $startDate)
                ->where('status', 'executed')
                ->select(
                    'base_currency',
                    'quote_currency',
                    DB::raw('SUM(amount) as volume'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('AVG(rate) as avg_rate'),
                    DB::raw('AVG(spread_bps) as avg_spread')
                )
                ->groupBy('base_currency', 'quote_currency')
                ->orderByDesc('volume')
                ->get();

            return [
                'period_days' => $days,
                'corridors' => $corridors->map(function ($c) {
                    return [
                        'corridor' => "{$c->base_currency}/{$c->quote_currency}",
                        'base_currency' => $c->base_currency,
                        'quote_currency' => $c->quote_currency,
                        'volume' => $c->volume,
                        'transaction_count' => $c->count,
                        'average_rate' => round($c->avg_rate, 6),
                        'average_spread_bps' => round($c->avg_spread ?? 0, 2),
                    ];
                })->toArray(),
            ];
        });
    }

    protected function getVolumeByCurrencyPair($quotes): array
    {
        return $quotes->groupBy(function ($quote) {
            return "{$quote->base_currency}/{$quote->quote_currency}";
        })->map(function ($group, $pair) {
            return [
                'pair' => $pair,
                'volume' => $group->sum('amount'),
                'count' => $group->count(),
            ];
        })->sortByDesc('volume')->values()->toArray();
    }

    protected function getVolumeByProvider($quotes): array
    {
        $byProvider = $quotes->groupBy('fx_provider_id');
        $providers = FxProvider::whereIn('id', $byProvider->keys())->get()->keyBy('id');

        return $byProvider->map(function ($group, $providerId) use ($providers) {
            return [
                'provider_id' => $providerId,
                'provider_name' => $providers[$providerId]->name ?? 'Unknown',
                'volume' => $group->sum('amount'),
                'count' => $group->count(),
            ];
        })->sortByDesc('volume')->values()->toArray();
    }

    protected function getHourlyDistribution($quotes): array
    {
        return $quotes->groupBy(function ($quote) {
            return $quote->created_at->format('H');
        })->map(function ($group, $hour) {
            return [
                'hour' => (int) $hour,
                'volume' => $group->sum('amount'),
                'count' => $group->count(),
            ];
        })->sortBy('hour')->values()->toArray();
    }

    protected function calculateVolatility(array $rates): float
    {
        if (count($rates) < 2) {
            return 0;
        }

        $mean = array_sum($rates) / count($rates);
        $squaredDiffs = array_map(fn($r) => pow($r - $mean, 2), $rates);
        $variance = array_sum($squaredDiffs) / count($rates);
        
        return round(sqrt($variance), 6);
    }
}

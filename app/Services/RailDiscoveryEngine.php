<?php

namespace App\Services;

use App\Models\PaymentRail;
use App\Models\RailAvailabilityLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RailDiscoveryEngine
{
    const CACHE_TTL = 300; // 5 minutes

    public function discoverAvailableRails(
        string $sourceCurrency,
        string $destinationCurrency,
        string $sourceCountry,
        string $destinationCountry,
        float $amount
    ): Collection {
        $cacheKey = "rails:{$sourceCurrency}:{$destinationCurrency}:{$sourceCountry}:{$destinationCountry}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $sourceCurrency, $destinationCurrency, $sourceCountry, $destinationCountry, $amount
        ) {
            return PaymentRail::available()
                ->forCurrency($sourceCurrency)
                ->forCountry($sourceCountry)
                ->byPriority()
                ->get()
                ->filter(function ($rail) use ($amount) {
                    return $rail->supportsAmount($amount) && $rail->isWithinOperatingHours();
                })
                ->map(function ($rail) use ($amount) {
                    return [
                        'rail' => $rail,
                        'fee' => $rail->calculateFee($amount),
                        'settlement_minutes' => $rail->typical_settlement_minutes,
                        'reliability_score' => $rail->reliability_score,
                        'supports_instant' => $rail->supports_instant,
                    ];
                });
        });
    }

    public function getBestRailForRoute(
        string $sourceCurrency,
        string $destinationCurrency,
        string $sourceCountry,
        string $destinationCountry,
        float $amount,
        string $priority = 'cost' // cost, speed, reliability
    ): ?array {
        $rails = $this->discoverAvailableRails(
            $sourceCurrency, $destinationCurrency, $sourceCountry, $destinationCountry, $amount
        );

        if ($rails->isEmpty()) {
            return null;
        }

        return match ($priority) {
            'speed' => $rails->sortBy('settlement_minutes')->first(),
            'reliability' => $rails->sortByDesc('reliability_score')->first(),
            default => $rails->sortBy('fee')->first(),
        };
    }

    public function checkRailHealth(PaymentRail $rail): array
    {
        $startTime = microtime(true);
        
        try {
            // Simulate health check - in production this would ping the actual provider
            $isHealthy = $this->performHealthCheck($rail);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            $status = $isHealthy ? PaymentRail::HEALTH_HEALTHY : PaymentRail::HEALTH_UNHEALTHY;
            $rail->updateHealthStatus($status, $responseTime);

            return [
                'rail_id' => $rail->id,
                'status' => $status,
                'response_time_ms' => $responseTime,
                'checked_at' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::error('Rail health check failed', [
                'rail_id' => $rail->id,
                'error' => $e->getMessage(),
            ]);

            $rail->updateHealthStatus(PaymentRail::HEALTH_UNHEALTHY);

            return [
                'rail_id' => $rail->id,
                'status' => PaymentRail::HEALTH_UNHEALTHY,
                'error' => $e->getMessage(),
                'checked_at' => now()->toIso8601String(),
            ];
        }
    }

    public function checkAllRailsHealth(): array
    {
        $rails = PaymentRail::active()->get();
        $results = [];

        foreach ($rails as $rail) {
            $results[] = $this->checkRailHealth($rail);
        }

        return [
            'checked_at' => now()->toIso8601String(),
            'total_rails' => count($results),
            'healthy' => collect($results)->where('status', PaymentRail::HEALTH_HEALTHY)->count(),
            'unhealthy' => collect($results)->where('status', PaymentRail::HEALTH_UNHEALTHY)->count(),
            'results' => $results,
        ];
    }

    public function getRailAvailabilityReport(int $hours = 24): array
    {
        $since = now()->subHours($hours);

        $logs = RailAvailabilityLog::where('checked_at', '>=', $since)
            ->with('rail')
            ->get()
            ->groupBy('payment_rail_id');

        $report = [];
        foreach ($logs as $railId => $railLogs) {
            $total = $railLogs->count();
            $available = $railLogs->where('status', 'available')->count();
            $avgResponseTime = $railLogs->whereNotNull('response_time_ms')->avg('response_time_ms');

            $report[] = [
                'rail_id' => $railId,
                'rail_name' => $railLogs->first()->rail->name ?? 'Unknown',
                'uptime_percentage' => $total > 0 ? round(($available / $total) * 100, 2) : 0,
                'avg_response_time_ms' => round($avgResponseTime ?? 0),
                'total_checks' => $total,
            ];
        }

        return [
            'period_hours' => $hours,
            'generated_at' => now()->toIso8601String(),
            'rails' => $report,
        ];
    }

    public function getRouteOptions(
        string $sourceCurrency,
        string $destinationCurrency,
        string $sourceCountry,
        string $destinationCountry,
        float $amount
    ): array {
        $rails = $this->discoverAvailableRails(
            $sourceCurrency, $destinationCurrency, $sourceCountry, $destinationCountry, $amount
        );

        return [
            'source' => [
                'currency' => $sourceCurrency,
                'country' => $sourceCountry,
            ],
            'destination' => [
                'currency' => $destinationCurrency,
                'country' => $destinationCountry,
            ],
            'amount' => $amount,
            'available_rails' => $rails->count(),
            'options' => $rails->map(function ($option) {
                return [
                    'rail_code' => $option['rail']->code,
                    'rail_name' => $option['rail']->name,
                    'type' => $option['rail']->type,
                    'fee' => $option['fee'],
                    'settlement_minutes' => $option['settlement_minutes'],
                    'reliability_score' => $option['reliability_score'],
                    'supports_instant' => $option['supports_instant'],
                ];
            })->values()->toArray(),
        ];
    }

    protected function performHealthCheck(PaymentRail $rail): bool
    {
        // In production, this would make actual API calls to check provider health
        // For MVP, simulate with high success rate
        return rand(1, 100) <= 98;
    }
}

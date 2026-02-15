<?php

namespace App\Services;

use App\Models\SloMetric;
use App\Models\SystemHealthCheck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthCheckService
{
    const SLO_TARGETS = [
        'local_transaction' => ['target_ms' => 500, 'success_rate' => 99.0],
        'cross_border_initiation' => ['target_ms' => 1000, 'success_rate' => 99.0],
        'fx_quote' => ['target_ms' => 300, 'success_rate' => 99.5],
        'payment_request' => ['target_ms' => 500, 'success_rate' => 99.0],
        'p2p_match' => ['target_ms' => 500, 'success_rate' => 99.0],
    ];

    const ALERT_THRESHOLDS = [
        'error_rate_percent' => 1.0,
        'queue_depth' => 1000,
        'success_rate_percent' => 95.0,
        'db_connections_percent' => 80.0,
    ];

    public function runAllHealthChecks(): array
    {
        $results = [];

        $results['database'] = $this->checkDatabase();
        $results['cache'] = $this->checkCache();
        $results['queue'] = $this->checkQueue();
        $results['storage'] = $this->checkStorage();

        $overallStatus = $this->calculateOverallStatus($results);

        return [
            'status' => $overallStatus,
            'services' => $results,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function checkDatabase(): array
    {
        $startTime = microtime(true);

        try {
            DB::select('SELECT 1');
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            $connections = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            $maxConnections = DB::select("SHOW VARIABLES LIKE 'max_connections'");

            $currentConnections = $connections[0]->Value ?? 0;
            $maxConnectionsValue = $maxConnections[0]->Value ?? 100;
            $connectionUsage = ($currentConnections / $maxConnectionsValue) * 100;

            $status = $responseTime < 100 && $connectionUsage < 80
                ? SystemHealthCheck::STATUS_HEALTHY
                : ($responseTime < 500 ? SystemHealthCheck::STATUS_DEGRADED : SystemHealthCheck::STATUS_UNHEALTHY);

            $this->recordHealthCheck(SystemHealthCheck::SERVICE_DATABASE, $status, $responseTime, [
                'current_connections' => $currentConnections,
                'max_connections' => $maxConnectionsValue,
                'connection_usage_percent' => round($connectionUsage, 2),
            ]);

            return [
                'status' => $status,
                'response_time_ms' => $responseTime,
                'metrics' => [
                    'connections' => $currentConnections,
                    'connection_usage_percent' => round($connectionUsage, 2),
                ],
            ];
        } catch (\Exception $e) {
            $this->recordHealthCheck(SystemHealthCheck::SERVICE_DATABASE, SystemHealthCheck::STATUS_UNHEALTHY, null, null, $e->getMessage());

            return [
                'status' => SystemHealthCheck::STATUS_UNHEALTHY,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function checkCache(): array
    {
        $startTime = microtime(true);

        try {
            $testKey = 'health_check_' . time();
            Cache::put($testKey, 'test', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if ($value !== 'test') {
                throw new \Exception('Cache read/write mismatch');
            }

            $status = $responseTime < 50
                ? SystemHealthCheck::STATUS_HEALTHY
                : ($responseTime < 200 ? SystemHealthCheck::STATUS_DEGRADED : SystemHealthCheck::STATUS_UNHEALTHY);

            $this->recordHealthCheck(SystemHealthCheck::SERVICE_CACHE, $status, $responseTime);

            return [
                'status' => $status,
                'response_time_ms' => $responseTime,
            ];
        } catch (\Exception $e) {
            $this->recordHealthCheck(SystemHealthCheck::SERVICE_CACHE, SystemHealthCheck::STATUS_UNHEALTHY, null, null, $e->getMessage());

            return [
                'status' => SystemHealthCheck::STATUS_UNHEALTHY,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function checkQueue(): array
    {
        try {
            $queueSize = Queue::size();

            $status = $queueSize < 100
                ? SystemHealthCheck::STATUS_HEALTHY
                : ($queueSize < self::ALERT_THRESHOLDS['queue_depth'] ? SystemHealthCheck::STATUS_DEGRADED : SystemHealthCheck::STATUS_UNHEALTHY);

            $this->recordHealthCheck(SystemHealthCheck::SERVICE_QUEUE, $status, null, [
                'queue_depth' => $queueSize,
            ]);

            return [
                'status' => $status,
                'metrics' => [
                    'queue_depth' => $queueSize,
                ],
            ];
        } catch (\Exception $e) {
            $this->recordHealthCheck(SystemHealthCheck::SERVICE_QUEUE, SystemHealthCheck::STATUS_UNHEALTHY, null, null, $e->getMessage());

            return [
                'status' => SystemHealthCheck::STATUS_UNHEALTHY,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function checkStorage(): array
    {
        $startTime = microtime(true);

        try {
            $testFile = 'health_check_' . time() . '.txt';
            Storage::put($testFile, 'test');
            $content = Storage::get($testFile);
            Storage::delete($testFile);

            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if ($content !== 'test') {
                throw new \Exception('Storage read/write mismatch');
            }

            $status = $responseTime < 100
                ? SystemHealthCheck::STATUS_HEALTHY
                : ($responseTime < 500 ? SystemHealthCheck::STATUS_DEGRADED : SystemHealthCheck::STATUS_UNHEALTHY);

            $this->recordHealthCheck(SystemHealthCheck::SERVICE_STORAGE, $status, $responseTime);

            return [
                'status' => $status,
                'response_time_ms' => $responseTime,
            ];
        } catch (\Exception $e) {
            $this->recordHealthCheck(SystemHealthCheck::SERVICE_STORAGE, SystemHealthCheck::STATUS_UNHEALTHY, null, null, $e->getMessage());

            return [
                'status' => SystemHealthCheck::STATUS_UNHEALTHY,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getSloReport(int $hours = 24): array
    {
        $report = [];

        foreach (self::SLO_TARGETS as $operation => $targets) {
            $avgResponseTime = SloMetric::getAverageResponseTime($operation, $hours);
            $p95ResponseTime = SloMetric::getP95ResponseTime($operation, $hours);
            $successRate = SloMetric::getSuccessRate($operation, $hours);

            $meetsTarget = $avgResponseTime <= $targets['target_ms'] && $successRate >= $targets['success_rate'];

            $report[$operation] = [
                'target_response_time_ms' => $targets['target_ms'],
                'target_success_rate' => $targets['success_rate'],
                'actual_avg_response_time_ms' => round($avgResponseTime, 2),
                'actual_p95_response_time_ms' => $p95ResponseTime,
                'actual_success_rate' => round($successRate, 2),
                'meets_slo' => $meetsTarget,
            ];
        }

        return [
            'period_hours' => $hours,
            'generated_at' => now()->toIso8601String(),
            'operations' => $report,
        ];
    }

    public function getAlertStatus(): array
    {
        $alerts = [];

        foreach (self::SLO_TARGETS as $operation => $targets) {
            $successRate = SloMetric::getSuccessRate($operation, 1);
            $errorRate = 100 - $successRate;

            if ($errorRate > self::ALERT_THRESHOLDS['error_rate_percent']) {
                $alerts[] = [
                    'type' => 'error_rate',
                    'operation' => $operation,
                    'current_value' => round($errorRate, 2),
                    'threshold' => self::ALERT_THRESHOLDS['error_rate_percent'],
                    'severity' => 'critical',
                ];
            }

            if ($successRate < self::ALERT_THRESHOLDS['success_rate_percent']) {
                $alerts[] = [
                    'type' => 'success_rate',
                    'operation' => $operation,
                    'current_value' => round($successRate, 2),
                    'threshold' => self::ALERT_THRESHOLDS['success_rate_percent'],
                    'severity' => 'warning',
                ];
            }
        }

        try {
            $queueDepth = Queue::size();
            if ($queueDepth > self::ALERT_THRESHOLDS['queue_depth']) {
                $alerts[] = [
                    'type' => 'queue_depth',
                    'current_value' => $queueDepth,
                    'threshold' => self::ALERT_THRESHOLDS['queue_depth'],
                    'severity' => 'warning',
                ];
            }
        } catch (\Exception $e) {
            Log::warning("Could not check queue depth", ['error' => $e->getMessage()]);
        }

        return [
            'has_alerts' => !empty($alerts),
            'alert_count' => count($alerts),
            'alerts' => $alerts,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function recordMetric(
        string $operation,
        int $responseTimeMs,
        bool $success,
        ?string $errorCode = null,
        ?string $endpoint = null,
        ?string $method = null
    ): void {
        SloMetric::record($operation, $responseTimeMs, $success, $errorCode, $endpoint, $method);
    }

    protected function recordHealthCheck(
        string $serviceName,
        string $status,
        ?int $responseTimeMs = null,
        ?array $metrics = null,
        ?string $errorMessage = null
    ): void {
        SystemHealthCheck::create([
            'service_name' => $serviceName,
            'status' => $status,
            'response_time_ms' => $responseTimeMs,
            'metrics' => $metrics,
            'error_message' => $errorMessage,
            'checked_at' => now(),
        ]);
    }

    protected function calculateOverallStatus(array $results): string
    {
        $statuses = array_column($results, 'status');

        if (in_array(SystemHealthCheck::STATUS_UNHEALTHY, $statuses)) {
            return SystemHealthCheck::STATUS_UNHEALTHY;
        }

        if (in_array(SystemHealthCheck::STATUS_DEGRADED, $statuses)) {
            return SystemHealthCheck::STATUS_DEGRADED;
        }

        return SystemHealthCheck::STATUS_HEALTHY;
    }

    public function getSystemStatus(): array
    {
        $healthCheck = $this->runAllHealthChecks();
        $sloReport = $this->getSloReport(24);
        $alertStatus = $this->getAlertStatus();

        return [
            'overall_status' => $healthCheck['status'],
            'health' => $healthCheck,
            'slo_summary' => $sloReport,
            'alerts' => $alertStatus,
        ];
    }
}

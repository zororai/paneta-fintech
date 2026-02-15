<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SloMetric extends Model
{
    public $timestamps = false;

    const OPERATION_LOCAL_TRANSACTION = 'local_transaction';
    const OPERATION_CROSS_BORDER_INITIATION = 'cross_border_initiation';
    const OPERATION_FX_QUOTE = 'fx_quote';
    const OPERATION_PAYMENT_REQUEST = 'payment_request';
    const OPERATION_P2P_MATCH = 'p2p_match';

    protected $fillable = [
        'operation',
        'response_time_ms',
        'success',
        'error_code',
        'endpoint',
        'method',
        'metadata',
        'recorded_at',
    ];

    protected $casts = [
        'response_time_ms' => 'integer',
        'success' => 'boolean',
        'metadata' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function scopeForOperation($query, string $operation)
    {
        return $query->where('operation', $operation);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    public static function record(
        string $operation,
        int $responseTimeMs,
        bool $success,
        ?string $errorCode = null,
        ?string $endpoint = null,
        ?string $method = null,
        ?array $metadata = null
    ): self {
        return static::create([
            'operation' => $operation,
            'response_time_ms' => $responseTimeMs,
            'success' => $success,
            'error_code' => $errorCode,
            'endpoint' => $endpoint,
            'method' => $method,
            'metadata' => $metadata,
            'recorded_at' => now(),
        ]);
    }

    public static function getAverageResponseTime(string $operation, int $hours = 24): float
    {
        return static::forOperation($operation)
            ->successful()
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->avg('response_time_ms') ?? 0;
    }

    public static function getSuccessRate(string $operation, int $hours = 24): float
    {
        $total = static::forOperation($operation)
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->count();

        if ($total === 0) {
            return 100.0;
        }

        $successful = static::forOperation($operation)
            ->successful()
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->count();

        return ($successful / $total) * 100;
    }

    public static function getP95ResponseTime(string $operation, int $hours = 24): int
    {
        $metrics = static::forOperation($operation)
            ->successful()
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->orderBy('response_time_ms')
            ->pluck('response_time_ms')
            ->toArray();

        if (empty($metrics)) {
            return 0;
        }

        $index = (int) ceil(count($metrics) * 0.95) - 1;
        return $metrics[$index] ?? 0;
    }
}

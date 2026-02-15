<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemHealthCheck extends Model
{
    const STATUS_HEALTHY = 'healthy';
    const STATUS_DEGRADED = 'degraded';
    const STATUS_UNHEALTHY = 'unhealthy';
    const STATUS_UNKNOWN = 'unknown';

    const SERVICE_DATABASE = 'database';
    const SERVICE_CACHE = 'cache';
    const SERVICE_QUEUE = 'queue';
    const SERVICE_STORAGE = 'storage';
    const SERVICE_MAIL = 'mail';
    const SERVICE_FX_PROVIDER = 'fx_provider';

    protected $fillable = [
        'service_name',
        'status',
        'response_time_ms',
        'metrics',
        'error_message',
        'checked_at',
    ];

    protected $casts = [
        'response_time_ms' => 'integer',
        'metrics' => 'array',
        'checked_at' => 'datetime',
    ];

    public function isHealthy(): bool
    {
        return $this->status === self::STATUS_HEALTHY;
    }

    public function isDegraded(): bool
    {
        return $this->status === self::STATUS_DEGRADED;
    }

    public function isUnhealthy(): bool
    {
        return $this->status === self::STATUS_UNHEALTHY;
    }

    public function scopeForService($query, string $serviceName)
    {
        return $query->where('service_name', $serviceName);
    }

    public function scopeRecent($query, int $minutes = 5)
    {
        return $query->where('checked_at', '>=', now()->subMinutes($minutes));
    }

    public function scopeUnhealthy($query)
    {
        return $query->where('status', self::STATUS_UNHEALTHY);
    }

    public static function getLatestForService(string $serviceName): ?self
    {
        return static::forService($serviceName)
            ->orderByDesc('checked_at')
            ->first();
    }

    public static function getAllLatest(): array
    {
        $services = [
            self::SERVICE_DATABASE,
            self::SERVICE_CACHE,
            self::SERVICE_QUEUE,
            self::SERVICE_STORAGE,
            self::SERVICE_MAIL,
            self::SERVICE_FX_PROVIDER,
        ];

        $results = [];
        foreach ($services as $service) {
            $results[$service] = static::getLatestForService($service);
        }

        return $results;
    }
}

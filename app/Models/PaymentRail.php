<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentRail extends Model
{
    use HasFactory;

    const TYPE_BANK_TRANSFER = 'bank_transfer';
    const TYPE_CARD_NETWORK = 'card_network';
    const TYPE_MOBILE_MONEY = 'mobile_money';
    const TYPE_WALLET = 'wallet';
    const TYPE_CRYPTO = 'crypto';
    const TYPE_SWIFT = 'swift';
    const TYPE_SEPA = 'sepa';
    const TYPE_ACH = 'ach';
    const TYPE_RTP = 'rtp';

    const HEALTH_HEALTHY = 'healthy';
    const HEALTH_DEGRADED = 'degraded';
    const HEALTH_UNHEALTHY = 'unhealthy';
    const HEALTH_MAINTENANCE = 'maintenance';

    protected $fillable = [
        'code',
        'name',
        'provider',
        'type',
        'supported_currencies',
        'supported_countries',
        'is_active',
        'supports_instant',
        'supports_scheduled',
        'min_amount',
        'max_amount',
        'base_fee',
        'percentage_fee',
        'typical_settlement_minutes',
        'priority',
        'reliability_score',
        'operating_hours',
        'metadata',
        'last_health_check',
        'health_status',
    ];

    protected $casts = [
        'supported_currencies' => 'array',
        'supported_countries' => 'array',
        'is_active' => 'boolean',
        'supports_instant' => 'boolean',
        'supports_scheduled' => 'boolean',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'base_fee' => 'decimal:4',
        'percentage_fee' => 'decimal:4',
        'reliability_score' => 'decimal:2',
        'operating_hours' => 'array',
        'metadata' => 'array',
        'last_health_check' => 'datetime',
    ];

    public function availabilityLogs(): HasMany
    {
        return $this->hasMany(RailAvailabilityLog::class);
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array($currency, $this->supported_currencies ?? []);
    }

    public function supportsCountry(string $country): bool
    {
        return in_array($country, $this->supported_countries ?? []);
    }

    public function supportsAmount(float $amount): bool
    {
        if ($this->min_amount && $amount < $this->min_amount) {
            return false;
        }
        if ($this->max_amount && $amount > $this->max_amount) {
            return false;
        }
        return true;
    }

    public function calculateFee(float $amount): float
    {
        return $this->base_fee + ($amount * $this->percentage_fee / 100);
    }

    public function isAvailable(): bool
    {
        return $this->is_active && $this->health_status === self::HEALTH_HEALTHY;
    }

    public function isWithinOperatingHours(): bool
    {
        if (empty($this->operating_hours)) {
            return true; // 24/7 if not specified
        }

        $now = now();
        $dayOfWeek = strtolower($now->format('D'));
        $currentTime = $now->format('H:i');

        if (!isset($this->operating_hours[$dayOfWeek])) {
            return false;
        }

        $hours = $this->operating_hours[$dayOfWeek];
        return $currentTime >= $hours['start'] && $currentTime <= $hours['end'];
    }

    public function updateHealthStatus(string $status, int $responseTimeMs = null): void
    {
        $this->update([
            'health_status' => $status,
            'last_health_check' => now(),
        ]);

        RailAvailabilityLog::create([
            'payment_rail_id' => $this->id,
            'status' => $status === self::HEALTH_HEALTHY ? 'available' : ($status === self::HEALTH_DEGRADED ? 'degraded' : 'unavailable'),
            'response_time_ms' => $responseTimeMs,
            'checked_at' => now(),
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeHealthy($query)
    {
        return $query->where('health_status', self::HEALTH_HEALTHY);
    }

    public function scopeAvailable($query)
    {
        return $query->active()->healthy();
    }

    public function scopeForCurrency($query, string $currency)
    {
        return $query->whereJsonContains('supported_currencies', $currency);
    }

    public function scopeForCountry($query, string $country)
    {
        return $query->whereJsonContains('supported_countries', $country);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc')->orderBy('reliability_score', 'desc');
    }
}

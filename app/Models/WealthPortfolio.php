<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WealthPortfolio extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'total_value',
        'base_currency',
        'asset_allocation',
        'currency_allocation',
        'risk_score',
        'last_calculated_at',
    ];

    protected $casts = [
        'total_value' => 'decimal:2',
        'asset_allocation' => 'array',
        'currency_allocation' => 'array',
        'risk_score' => 'decimal:2',
        'last_calculated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRiskLevel(): string
    {
        if ($this->risk_score === null) {
            return 'unknown';
        }

        if ($this->risk_score < 30) {
            return 'low';
        } elseif ($this->risk_score < 60) {
            return 'medium';
        } else {
            return 'high';
        }
    }

    public function isStale(): bool
    {
        if (!$this->last_calculated_at) {
            return true;
        }
        return $this->last_calculated_at->lt(now()->subHours(24));
    }

    public function updateCalculation(array $data): void
    {
        $this->update(array_merge($data, [
            'last_calculated_at' => now(),
        ]));
    }

    public function getCurrencyPercentage(string $currency): float
    {
        $allocation = $this->currency_allocation ?? [];
        return $allocation[$currency] ?? 0;
    }

    public function getDiversificationScore(): float
    {
        $allocation = $this->currency_allocation ?? [];
        
        if (empty($allocation)) {
            return 0;
        }

        $count = count($allocation);
        $maxPercentage = max($allocation);

        return min(100, ($count * 10) + (100 - $maxPercentage));
    }
}

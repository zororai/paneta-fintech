<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FxProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'country',
        'is_active',
        'risk_score',
        'default_spread_percentage',
        'supported_pairs',
        'api_endpoint',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'risk_score' => 'integer',
        'default_spread_percentage' => 'decimal:4',
        'supported_pairs' => 'array',
    ];

    public function quotes(): HasMany
    {
        return $this->hasMany(FxQuote::class);
    }

    public function crossBorderTransactions(): HasMany
    {
        return $this->hasMany(CrossBorderTransactionIntent::class);
    }

    public function supportsPair(string $baseCurrency, string $quoteCurrency): bool
    {
        if (!$this->supported_pairs) {
            return true;
        }

        $pair = "{$baseCurrency}/{$quoteCurrency}";
        return in_array($pair, $this->supported_pairs);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowRisk($query, int $maxScore = 50)
    {
        return $query->where('risk_score', '<=', $maxScore);
    }

    public function scopeOrderedByRisk($query)
    {
        return $query->orderBy('risk_score', 'asc');
    }
}

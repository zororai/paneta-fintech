<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FxQuote extends Model
{
    use HasFactory;

    protected $fillable = [
        'fx_provider_id',
        'base_currency',
        'quote_currency',
        'rate',
        'bid_rate',
        'ask_rate',
        'spread_percentage',
        'expires_at',
    ];

    protected $casts = [
        'rate' => 'decimal:8',
        'bid_rate' => 'decimal:8',
        'ask_rate' => 'decimal:8',
        'spread_percentage' => 'decimal:4',
        'expires_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(FxProvider::class, 'fx_provider_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    public function convertAmount(float $amount): float
    {
        return $amount * $this->rate;
    }

    public function getPairAttribute(): string
    {
        return "{$this->base_currency}/{$this->quote_currency}";
    }

    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeForPair($query, string $baseCurrency, string $quoteCurrency)
    {
        return $query->where('base_currency', $baseCurrency)
                     ->where('quote_currency', $quoteCurrency);
    }

    public function scopeBestRate($query)
    {
        return $query->orderBy('rate', 'desc');
    }
}

<?php

namespace App\Models;

use App\Enums\FxOfferStatus;
use App\Exceptions\InvalidStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FxOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_account_id',
        'destination_account_id',
        'sell_currency',
        'buy_currency',
        'rate',
        'amount',
        'min_amount',
        'filled_amount',
        'status',
        'settlement_methods',
        'matched_offer_id',
        'matched_user_id',
        'expires_at',
        'idempotency_key',
    ];

    protected $casts = [
        'rate' => 'decimal:8',
        'amount' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'filled_amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'settlement_methods' => 'array',
    ];

    const STATE_TRANSITIONS = [
        'open' => ['partially_filled', 'matched', 'cancelled', 'expired'],
        'partially_filled' => ['matched', 'cancelled', 'expired'],
        'matched' => ['executed', 'failed'],
        'executed' => [],
        'cancelled' => [],
        'expired' => [],
        'failed' => [],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class, 'source_account_id');
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class, 'destination_account_id');
    }

    public function matchedOffer(): BelongsTo
    {
        return $this->belongsTo(FxOffer::class, 'matched_offer_id');
    }

    public function matchedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_user_id');
    }

    public function transitionTo(string $newStatus): void
    {
        $allowed = self::STATE_TRANSITIONS[$this->status] ?? [];

        if (!in_array($newStatus, $allowed)) {
            throw new InvalidStateTransitionException(
                "Cannot transition from {$this->status} to {$newStatus}"
            );
        }

        $this->status = $newStatus;
        $this->save();
    }

    public function getRemainingAmount(): float
    {
        return max(0, $this->amount - $this->filled_amount);
    }

    public function canMatch(FxOffer $counterOffer): bool
    {
        return $this->sell_currency === $counterOffer->buy_currency
            && $this->buy_currency === $counterOffer->sell_currency
            && $this->user_id !== $counterOffer->user_id
            && $this->status === 'open'
            && $counterOffer->status === 'open';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getPairAttribute(): string
    {
        return "{$this->sell_currency}/{$this->buy_currency}";
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open')
                     ->where(function ($q) {
                         $q->whereNull('expires_at')
                           ->orWhere('expires_at', '>', now());
                     });
    }

    public function scopeForPair($query, string $sellCurrency, string $buyCurrency)
    {
        return $query->where('sell_currency', $sellCurrency)
                     ->where('buy_currency', $buyCurrency);
    }

    public function scopeMatchable($query, string $buyCurrency, string $sellCurrency, float $minRate)
    {
        return $query->open()
                     ->where('sell_currency', $buyCurrency)
                     ->where('buy_currency', $sellCurrency)
                     ->where('rate', '>=', 1 / $minRate);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExchangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fx_provider_id',
        'user_source_account_id',
        'user_destination_account_id',
        'provider_source_account_id',
        'sell_currency',
        'buy_currency',
        'sell_amount',
        'buy_amount',
        'exchange_rate',
        'provider_fee',
        'platform_fee',
        'total_fees',
        'status',
        'accepted_at',
        'rejected_at',
        'user_paid_at',
        'provider_paid_at',
        'completed_at',
        'expires_at',
        'rejection_reason',
        'notes',
        'reference_number',
    ];

    protected $casts = [
        'sell_amount' => 'decimal:2',
        'buy_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'provider_fee' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'total_fees' => 'decimal:2',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'user_paid_at' => 'datetime',
        'provider_paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($exchangeRequest) {
            if (!$exchangeRequest->reference_number) {
                $exchangeRequest->reference_number = 'EXR-' . strtoupper(Str::random(12));
            }
            if (!$exchangeRequest->expires_at) {
                $exchangeRequest->expires_at = now()->addHours(24);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fxProvider(): BelongsTo
    {
        return $this->belongsTo(FxProvider::class);
    }

    public function userSourceAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class, 'user_source_account_id');
    }

    public function userDestinationAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class, 'user_destination_account_id');
    }

    public function providerSourceAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class, 'provider_source_account_id');
    }

    // Status check methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isUserPaid(): bool
    {
        return $this->status === 'user_paid';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast() && $this->isPending();
    }

    // Action methods
    public function accept(): void
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    public function reject(string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function markUserPaid(): void
    {
        $this->update([
            'status' => 'user_paid',
            'user_paid_at' => now(),
        ]);
    }

    public function markProviderPaid(int $providerSourceAccountId): void
    {
        $this->update([
            'status' => 'provider_paid',
            'provider_paid_at' => now(),
            'provider_source_account_id' => $providerSourceAccountId,
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForProvider($query, int $providerId)
    {
        return $query->where('fx_provider_id', $providerId);
    }

    public function scopeAwaitingUserPayment($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeAwaitingProviderPayment($query)
    {
        return $query->where('status', 'user_paid');
    }

    // Computed attributes
    public function getCurrencyPairAttribute(): string
    {
        return "{$this->sell_currency}/{$this->buy_currency}";
    }

    public function getNetAmountAttribute(): float
    {
        return $this->buy_amount - $this->total_fees;
    }
}

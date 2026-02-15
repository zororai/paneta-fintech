<?php

namespace App\Models;

use App\Enums\CrossBorderTransactionStatus;
use App\Exceptions\InvalidStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CrossBorderTransactionIntent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_account_id',
        'destination_identifier',
        'destination_country',
        'source_currency',
        'destination_currency',
        'source_amount',
        'destination_amount',
        'fx_rate',
        'fx_provider_id',
        'fx_quote_id',
        'fee_amount',
        'fee_currency',
        'status',
        'reference',
        'idempotency_key',
        'leg_statuses',
        'failure_reason',
    ];

    protected $casts = [
        'source_amount' => 'decimal:2',
        'destination_amount' => 'decimal:2',
        'fx_rate' => 'decimal:8',
        'fee_amount' => 'decimal:2',
        'leg_statuses' => 'array',
    ];

    const STATE_TRANSITIONS = [
        'pending' => ['fx_locked', 'failed'],
        'fx_locked' => ['source_debited', 'failed', 'rolled_back'],
        'source_debited' => ['fx_executed', 'failed', 'rolled_back'],
        'fx_executed' => ['destination_credited', 'failed', 'rolled_back'],
        'destination_credited' => ['completed', 'failed'],
        'completed' => [],
        'failed' => ['rolled_back'],
        'rolled_back' => [],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class, 'source_account_id');
    }

    public function fxProvider(): BelongsTo
    {
        return $this->belongsTo(FxProvider::class);
    }

    public function fxQuote(): BelongsTo
    {
        return $this->belongsTo(FxQuote::class);
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

    public function updateLegStatus(string $leg, string $status): void
    {
        $legStatuses = $this->leg_statuses ?? [];
        $legStatuses[$leg] = [
            'status' => $status,
            'updated_at' => now()->toISOString(),
        ];
        $this->leg_statuses = $legStatuses;
        $this->save();
    }

    public function markFailed(string $reason): void
    {
        $this->failure_reason = $reason;
        $this->transitionTo('failed');
    }

    public static function generateReference(): string
    {
        return 'CBX-' . strtoupper(Str::random(12));
    }

    public function getTotalDebitAmount(): float
    {
        return $this->source_amount + $this->fee_amount;
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'fx_locked', 'source_debited', 'fx_executed', 'destination_credited']);
    }
}

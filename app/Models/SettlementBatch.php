<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementBatch extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_PARTIALLY_COMPLETED = 'partially_completed';

    const TYPE_MERCHANT_PAYOUT = 'merchant_payout';
    const TYPE_FX_SETTLEMENT = 'fx_settlement';
    const TYPE_REFUND_BATCH = 'refund_batch';
    const TYPE_FEE_COLLECTION = 'fee_collection';

    protected $fillable = [
        'batch_reference',
        'status',
        'batch_type',
        'currency',
        'total_amount',
        'total_fees',
        'net_amount',
        'transaction_count',
        'successful_count',
        'failed_count',
        'scheduled_at',
        'processing_started_at',
        'completed_at',
        'processed_by',
        'metadata',
        'failure_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_fees' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'scheduled_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SettlementBatchItem::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function startProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'processing_started_at' => now(),
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => $this->failed_count > 0 ? self::STATUS_PARTIALLY_COMPLETED : self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
            'completed_at' => now(),
        ]);
    }

    public function recalculateTotals(): void
    {
        $items = $this->items;
        $this->update([
            'transaction_count' => $items->count(),
            'total_amount' => $items->sum('amount'),
            'total_fees' => $items->sum('fee'),
            'net_amount' => $items->sum('net_amount'),
            'successful_count' => $items->where('status', 'completed')->count(),
            'failed_count' => $items->where('status', 'failed')->count(),
        ]);
    }

    public static function generateReference(): string
    {
        return 'SETL-' . strtoupper(bin2hex(random_bytes(8)));
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeScheduledForProcessing($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('scheduled_at', '<=', now());
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('batch_type', $type);
    }
}

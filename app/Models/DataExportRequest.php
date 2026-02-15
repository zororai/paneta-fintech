<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataExportRequest extends Model
{
    const TYPE_DATA_EXPORT = 'data_export';
    const TYPE_DATA_ACCESS = 'data_access';
    const TYPE_DATA_DELETION = 'data_deletion';
    const TYPE_DATA_RECTIFICATION = 'data_rectification';

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'request_type',
        'status',
        'requested_data_types',
        'download_url',
        'download_expires_at',
        'processed_at',
        'processed_by',
        'rejection_reason',
        'metadata',
    ];

    protected $casts = [
        'requested_data_types' => 'array',
        'download_expires_at' => 'datetime',
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isDownloadExpired(): bool
    {
        return $this->download_expires_at && $this->download_expires_at->isPast();
    }

    public function markAsProcessing(): self
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
        return $this;
    }

    public function markAsCompleted(string $downloadUrl, User $processor): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'download_url' => $downloadUrl,
            'download_expires_at' => now()->addDays(7),
            'processed_at' => now(),
            'processed_by' => $processor->id,
        ]);
        return $this;
    }

    public function markAsRejected(string $reason, User $processor): self
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'processed_at' => now(),
            'processed_by' => $processor->id,
        ]);
        return $this;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('request_type', $type);
    }
}

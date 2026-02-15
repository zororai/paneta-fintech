<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeadLetterJob extends Model
{
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_RETRYING = 'retrying';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_ABANDONED = 'abandoned';

    protected $fillable = [
        'uuid',
        'queue',
        'job_class',
        'payload',
        'exception',
        'attempts',
        'failed_at',
        'last_retry_at',
        'retry_count',
        'status',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'failed_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function getDecodedPayloadAttribute(): array
    {
        return json_decode($this->payload, true) ?? [];
    }

    public function isPendingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function markAsRetrying(): self
    {
        $this->update([
            'status' => self::STATUS_RETRYING,
            'last_retry_at' => now(),
            'retry_count' => $this->retry_count + 1,
        ]);
        return $this;
    }

    public function markAsResolved(User $user, ?string $notes = null): self
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_by' => $user->id,
            'resolution_notes' => $notes,
        ]);
        return $this;
    }

    public function markAsAbandoned(User $user, ?string $notes = null): self
    {
        $this->update([
            'status' => self::STATUS_ABANDONED,
            'resolved_by' => $user->id,
            'resolution_notes' => $notes,
        ]);
        return $this;
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', self::STATUS_PENDING_REVIEW);
    }

    public function scopeForQueue($query, string $queue)
    {
        return $query->where('queue', $queue);
    }

    public function scopeForJobClass($query, string $jobClass)
    {
        return $query->where('job_class', $jobClass);
    }
}

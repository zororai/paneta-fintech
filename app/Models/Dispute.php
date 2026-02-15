<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Dispute extends Model
{
    use HasFactory;

    const STATUS_OPENED = 'opened';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_EVIDENCE_REQUESTED = 'evidence_requested';
    const STATUS_EVIDENCE_SUBMITTED = 'evidence_submitted';
    const STATUS_ESCALATED = 'escalated';
    const STATUS_RESOLVED_IN_FAVOR = 'resolved_in_favor';
    const STATUS_RESOLVED_AGAINST = 'resolved_against';
    const STATUS_WITHDRAWN = 'withdrawn';
    const STATUS_EXPIRED = 'expired';

    const TYPE_UNAUTHORIZED = 'unauthorized_transaction';
    const TYPE_DUPLICATE = 'duplicate_charge';
    const TYPE_AMOUNT_DISCREPANCY = 'amount_discrepancy';
    const TYPE_SERVICE_NOT_RENDERED = 'service_not_rendered';
    const TYPE_PRODUCT_NOT_RECEIVED = 'product_not_received';
    const TYPE_QUALITY_ISSUE = 'quality_issue';
    const TYPE_REFUND_NOT_RECEIVED = 'refund_not_received';
    const TYPE_OTHER = 'other';

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_CRITICAL = 'critical';

    const STATE_TRANSITIONS = [
        self::STATUS_OPENED => [self::STATUS_UNDER_REVIEW, self::STATUS_WITHDRAWN],
        self::STATUS_UNDER_REVIEW => [self::STATUS_EVIDENCE_REQUESTED, self::STATUS_ESCALATED, self::STATUS_RESOLVED_IN_FAVOR, self::STATUS_RESOLVED_AGAINST],
        self::STATUS_EVIDENCE_REQUESTED => [self::STATUS_EVIDENCE_SUBMITTED, self::STATUS_EXPIRED],
        self::STATUS_EVIDENCE_SUBMITTED => [self::STATUS_UNDER_REVIEW, self::STATUS_ESCALATED],
        self::STATUS_ESCALATED => [self::STATUS_RESOLVED_IN_FAVOR, self::STATUS_RESOLVED_AGAINST],
        self::STATUS_RESOLVED_IN_FAVOR => [],
        self::STATUS_RESOLVED_AGAINST => [],
        self::STATUS_WITHDRAWN => [],
        self::STATUS_EXPIRED => [],
    ];

    protected $fillable = [
        'reference',
        'user_id',
        'disputable_type',
        'disputable_id',
        'status',
        'type',
        'priority',
        'disputed_amount',
        'currency',
        'description',
        'resolution_notes',
        'resolved_amount',
        'assigned_to',
        'resolved_by',
        'evidence_deadline',
        'escalated_at',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'disputed_amount' => 'decimal:2',
        'resolved_amount' => 'decimal:2',
        'evidence_deadline' => 'datetime',
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function disputable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(DisputeEvidence::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DisputeComment::class);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::STATE_TRANSITIONS[$this->status] ?? [];
        return in_array($newStatus, $allowed);
    }

    public function transitionTo(string $newStatus): void
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException("Cannot transition from {$this->status} to {$newStatus}");
        }
        $this->update(['status' => $newStatus]);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_OPENED,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_EVIDENCE_REQUESTED,
            self::STATUS_EVIDENCE_SUBMITTED,
            self::STATUS_ESCALATED,
        ]);
    }

    public function isResolved(): bool
    {
        return in_array($this->status, [
            self::STATUS_RESOLVED_IN_FAVOR,
            self::STATUS_RESOLVED_AGAINST,
            self::STATUS_WITHDRAWN,
            self::STATUS_EXPIRED,
        ]);
    }

    public function resolve(string $resolution, float $amount = null, User $resolver = null, string $notes = null): void
    {
        $this->update([
            'status' => $resolution,
            'resolved_amount' => $amount,
            'resolved_by' => $resolver?->id,
            'resolution_notes' => $notes,
            'resolved_at' => now(),
        ]);
    }

    public static function generateReference(): string
    {
        return 'DSP-' . strtoupper(bin2hex(random_bytes(6)));
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [
            self::STATUS_OPENED,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_EVIDENCE_REQUESTED,
            self::STATUS_EVIDENCE_SUBMITTED,
            self::STATUS_ESCALATED,
        ]);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeAssignedTo($query, User $user)
    {
        return $query->where('assigned_to', $user->id);
    }

    public function scopeEvidenceDeadlinePassed($query)
    {
        return $query->where('status', self::STATUS_EVIDENCE_REQUESTED)
            ->where('evidence_deadline', '<', now());
    }
}

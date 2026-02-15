<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ComplianceCase extends Model
{
    use HasFactory;

    const TYPE_AML_ALERT = 'aml_alert';
    const TYPE_SANCTIONS_HIT = 'sanctions_hit';
    const TYPE_PEP_MATCH = 'pep_match';
    const TYPE_ADVERSE_MEDIA = 'adverse_media';
    const TYPE_UNUSUAL_ACTIVITY = 'unusual_activity';
    const TYPE_THRESHOLD_BREACH = 'threshold_breach';
    const TYPE_KYC_REVIEW = 'kyc_review';
    const TYPE_KYB_REVIEW = 'kyb_review';
    const TYPE_FRAUD_SUSPICION = 'fraud_suspicion';
    const TYPE_REGULATORY_INQUIRY = 'regulatory_inquiry';
    const TYPE_SAR_FILING = 'sar_filing';

    const STATUS_OPEN = 'open';
    const STATUS_UNDER_INVESTIGATION = 'under_investigation';
    const STATUS_PENDING_INFO = 'pending_info';
    const STATUS_ESCALATED = 'escalated';
    const STATUS_SAR_FILED = 'sar_filed';
    const STATUS_CLOSED_NO_ACTION = 'closed_no_action';
    const STATUS_CLOSED_ACTION_TAKEN = 'closed_action_taken';
    const STATUS_CLOSED_FALSE_POSITIVE = 'closed_false_positive';

    protected $fillable = [
        'case_reference',
        'user_id',
        'related_type',
        'related_id',
        'type',
        'status',
        'priority',
        'risk_level',
        'description',
        'investigation_notes',
        'resolution_summary',
        'action_taken',
        'amount_involved',
        'currency',
        'assigned_to',
        'escalated_to',
        'closed_by',
        'sar_reference',
        'due_date',
        'escalated_at',
        'closed_at',
        'evidence_ids',
        'metadata',
    ];

    protected $casts = [
        'amount_involved' => 'decimal:2',
        'due_date' => 'datetime',
        'escalated_at' => 'datetime',
        'closed_at' => 'datetime',
        'evidence_ids' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ComplianceCaseNote::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_OPEN,
            self::STATUS_UNDER_INVESTIGATION,
            self::STATUS_PENDING_INFO,
            self::STATUS_ESCALATED,
        ]);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [
            self::STATUS_CLOSED_NO_ACTION,
            self::STATUS_CLOSED_ACTION_TAKEN,
            self::STATUS_CLOSED_FALSE_POSITIVE,
            self::STATUS_SAR_FILED,
        ]);
    }

    public function escalate(User $escalatedTo, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_ESCALATED,
            'escalated_to' => $escalatedTo->id,
            'escalated_at' => now(),
        ]);

        $this->notes()->create([
            'user_id' => auth()->id() ?? $escalatedTo->id,
            'note' => "Case escalated: {$reason}",
            'note_type' => 'escalation',
        ]);
    }

    public function close(string $status, string $summary, User $closedBy, string $actionTaken = null): void
    {
        $this->update([
            'status' => $status,
            'resolution_summary' => $summary,
            'action_taken' => $actionTaken,
            'closed_by' => $closedBy->id,
            'closed_at' => now(),
        ]);

        $this->notes()->create([
            'user_id' => $closedBy->id,
            'note' => "Case closed: {$summary}",
            'note_type' => 'closure',
        ]);
    }

    public static function generateReference(): string
    {
        return 'CMP-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [
            self::STATUS_OPEN,
            self::STATUS_UNDER_INVESTIGATION,
            self::STATUS_PENDING_INFO,
            self::STATUS_ESCALATED,
        ]);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'critical']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())->open();
    }

    public function scopeAssignedTo($query, User $user)
    {
        return $query->where('assigned_to', $user->id);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasUuids;

    const SEVERITY_INFO = 'info';
    const SEVERITY_SUCCESS = 'success';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_ERROR = 'error';

    const TYPE_TRANSACTION_EXECUTED = 'transaction_executed';
    const TYPE_CROSS_BORDER_COMPLETED = 'cross_border_completed';
    const TYPE_PAYMENT_REQUEST_PAID = 'payment_request_paid';
    const TYPE_SUBSCRIPTION_EXPIRING = 'subscription_expiring';
    const TYPE_SUSPICIOUS_ACTIVITY = 'suspicious_activity';
    const TYPE_KYC_VERIFIED = 'kyc_verified';
    const TYPE_ACCOUNT_LINKED = 'account_linked';
    const TYPE_FX_OFFER_MATCHED = 'fx_offer_matched';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'action_url',
        'data',
        'severity',
        'read_at',
        'sent_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): self
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
        return $this;
    }

    public function markAsSent(): self
    {
        if (!$this->sent_at) {
            $this->update(['sent_at' => now()]);
        }
        return $this;
    }

    public function getIsReadAttribute(): bool
    {
        return $this->read_at !== null;
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}

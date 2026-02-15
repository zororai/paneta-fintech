<?php

namespace App\Models;

use App\Enums\PaymentRequestStatus;
use App\Exceptions\InvalidStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'linked_account_id',
        'amount',
        'currency',
        'amount_received',
        'status',
        'reference',
        'qr_code_data',
        'description',
        'allow_partial',
        'expires_at',
        'idempotency_key',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'allow_partial' => 'boolean',
        'expires_at' => 'datetime',
    ];

    const STATE_TRANSITIONS = [
        'pending' => ['partially_fulfilled', 'completed', 'expired', 'cancelled'],
        'partially_fulfilled' => ['completed', 'expired', 'cancelled'],
        'completed' => [],
        'expired' => [],
        'cancelled' => [],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function linkedAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class);
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

    public function recordPayment(float $amount): void
    {
        $this->amount_received += $amount;
        
        if ($this->amount_received >= $this->amount) {
            $this->transitionTo('completed');
        } elseif ($this->amount_received > 0 && $this->allow_partial) {
            $this->transitionTo('partially_fulfilled');
        }
        
        $this->save();
    }

    public function getRemainingAmount(): float
    {
        return max(0, $this->amount - $this->amount_received);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isFulfilled(): bool
    {
        return $this->status === 'completed';
    }

    public static function generateReference(): string
    {
        return 'PR-' . strtoupper(Str::random(10));
    }

    public function generateQrCodeData(): string
    {
        return json_encode([
            'type' => 'paneta_payment_request',
            'reference' => $this->reference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'recipient' => $this->user->name,
        ]);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'partially_fulfilled'])
                     ->where(function ($q) {
                         $q->whereNull('expires_at')
                           ->orWhere('expires_at', '>', now());
                     });
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}

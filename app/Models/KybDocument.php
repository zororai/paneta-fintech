<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KybDocument extends Model
{
    use HasFactory;

    const TYPE_CERTIFICATE_OF_INCORPORATION = 'certificate_of_incorporation';
    const TYPE_BUSINESS_REGISTRATION = 'business_registration';
    const TYPE_TAX_CERTIFICATE = 'tax_certificate';
    const TYPE_PROOF_OF_ADDRESS = 'proof_of_address';
    const TYPE_SHAREHOLDER_REGISTER = 'shareholder_register';
    const TYPE_DIRECTOR_ID = 'director_id';
    const TYPE_BANK_STATEMENT = 'bank_statement';
    const TYPE_FINANCIAL_STATEMENT = 'financial_statement';
    const TYPE_LICENSE = 'license';
    const TYPE_MEMORANDUM = 'memorandum_of_association';

    const STATUS_PENDING = 'pending';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'merchant_id',
        'document_type',
        'document_number',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'status',
        'rejection_reason',
        'issue_date',
        'expiry_date',
        'issuing_authority',
        'issuing_country',
        'reviewed_by',
        'reviewed_at',
        'extracted_data',
        'metadata',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'reviewed_at' => 'datetime',
        'extracted_data' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function approve(User $reviewer): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }

    public function reject(User $reviewer, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForMerchant($query, Merchant $merchant)
    {
        return $query->where('merchant_id', $merchant->id);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('document_type', $type);
    }
}

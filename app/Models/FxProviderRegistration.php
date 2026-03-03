<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FxProviderRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trading_name',
        'trading_volume',
        'daily_limit',
        'licenses_path',
        'certificates_path',
        'license_validity',
        'email',
        'phone',
        'physical_address',
        'country_of_origin',
        'settlement_accounts',
        'key_services',
        'member_since',
        'trading_as',
        'processing_fee',
        'tax_clearance_path',
        'tax_id',
        'status',
        'admin_notes',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'license_validity' => 'date',
        'member_since' => 'date',
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}

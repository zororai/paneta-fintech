<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'business_registration_number',
        'business_type',
        'country',
        'kyb_status',
        'default_currency',
        'settlement_account_id',
        'transaction_fee_percentage',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'transaction_fee_percentage' => 'decimal:4',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function settlementAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class, 'settlement_account_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(MerchantDevice::class);
    }

    public function isVerified(): bool
    {
        return $this->kyb_status === 'verified';
    }

    public function canAcceptPayments(): bool
    {
        return $this->is_active && $this->isVerified() && $this->settlement_account_id;
    }

    public function calculateFee(float $amount): float
    {
        return round($amount * ($this->transaction_fee_percentage / 100), 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('kyb_status', 'verified');
    }
}

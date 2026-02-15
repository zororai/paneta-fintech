<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MerchantDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'device_identifier',
        'device_name',
        'device_type',
        'status',
        'last_active_at',
        'ip_address',
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function recordActivity(?string $ipAddress = null): void
    {
        $this->update([
            'last_active_at' => now(),
            'ip_address' => $ipAddress ?? $this->ip_address,
        ]);
    }

    public static function generateDeviceIdentifier(): string
    {
        return 'DEV-' . strtoupper(Str::random(16));
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

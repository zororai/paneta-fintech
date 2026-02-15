<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformLedger extends Model
{
    const UPDATED_AT = null;

    const TYPE_FEE = 'fee';
    const TYPE_REFUND = 'refund';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_WRITE_OFF = 'write_off';

    protected $table = 'platform_ledger';

    protected $fillable = [
        'entry_type',
        'reference_type',
        'reference_id',
        'amount',
        'currency',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function getIsDebitAttribute(): bool
    {
        return in_array($this->entry_type, [self::TYPE_REFUND, self::TYPE_WRITE_OFF]);
    }

    public function getIsCreditAttribute(): bool
    {
        return in_array($this->entry_type, [self::TYPE_FEE, self::TYPE_ADJUSTMENT]);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('entry_type', $type);
    }

    public function scopeByCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}

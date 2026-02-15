<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeLedger extends Model
{
    use HasFactory;

    protected $table = 'fee_ledger';

    protected $fillable = [
        'user_id',
        'transaction_type',
        'transaction_id',
        'amount',
        'currency',
        'fee_percentage',
        'fee_type',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_percentage' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCollected($query)
    {
        return $query->where('status', 'collected');
    }

    public function scopeForCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public static function calculateTotalRevenue(string $currency = null, $startDate = null, $endDate = null): float
    {
        $query = self::collected();

        if ($currency) {
            $query->forCurrency($currency);
        }

        if ($startDate && $endDate) {
            $query->inPeriod($startDate, $endDate);
        }

        return $query->sum('amount');
    }
}

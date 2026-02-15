<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AggregatedTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'aggregated_account_id',
        'external_reference',
        'amount',
        'currency',
        'description',
        'transaction_type',
        'transaction_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    public function aggregatedAccount(): BelongsTo
    {
        return $this->belongsTo(AggregatedAccount::class);
    }

    public function isCredit(): bool
    {
        return $this->amount > 0;
    }

    public function isDebit(): bool
    {
        return $this->amount < 0;
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('transaction_date', '>=', now()->subDays($days));
    }
}

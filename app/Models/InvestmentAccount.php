<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'linked_account_id',
        'provider_name',
        'account_number',
        'account_type',
        'currency',
        'total_value',
        'cash_balance',
        'invested_value',
        'unrealized_gain_loss',
        'day_change',
        'day_change_percent',
        'last_synced_at',
        'holdings',
        'metadata',
    ];

    protected $casts = [
        'total_value' => 'decimal:2',
        'cash_balance' => 'decimal:2',
        'invested_value' => 'decimal:2',
        'unrealized_gain_loss' => 'decimal:2',
        'day_change' => 'decimal:2',
        'day_change_percent' => 'decimal:4',
        'last_synced_at' => 'datetime',
        'holdings' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function linkedAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class);
    }

    public function getReturnPercentage(): float
    {
        if ($this->invested_value <= 0) {
            return 0;
        }
        return round(($this->unrealized_gain_loss / $this->invested_value) * 100, 2);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }
}

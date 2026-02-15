<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AggregatedAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'institution_id',
        'external_account_id',
        'currency',
        'available_balance',
        'last_refreshed_at',
        'status',
    ];

    protected $casts = [
        'available_balance' => 'decimal:2',
        'last_refreshed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AggregatedTransaction::class);
    }

    public function isStale(): bool
    {
        if (!$this->last_refreshed_at) {
            return true;
        }
        return $this->last_refreshed_at->lt(now()->subHours(4));
    }

    public function markRefreshed(): void
    {
        $this->update([
            'last_refreshed_at' => now(),
            'status' => 'active',
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LinkedAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'institution_id',
        'country',
        'account_identifier',
        'account_holder_name',
        'currency',
        'mock_balance',
        'consent_token',
        'consent_expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'mock_balance' => 'decimal:2',
            'consent_token' => 'encrypted',
            'consent_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function transactionIntents(): HasMany
    {
        return $this->hasMany(TransactionIntent::class, 'issuer_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isConsentValid(): bool
    {
        return $this->status === 'active' && $this->consent_expires_at->isFuture();
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->mock_balance >= $amount;
    }
}

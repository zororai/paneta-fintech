<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class P2POffer extends Model
{
    protected $fillable = [
        'user_id',
        'source_account_id',
        'destination_account_id',
        'sell_currency',
        'buy_currency',
        'rate',
        'amount',
        'min_amount',
        'settlement_methods',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'amount' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'settlement_methods' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class, 'source_account_id');
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class, 'destination_account_id');
    }

    public function exchangeRequests(): HasMany
    {
        return $this->hasMany(P2PExchangeRequest::class, 'offer_id');
    }
}

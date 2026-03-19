<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class P2PExchangeRequest extends Model
{
    protected $table = 'p2p_exchange_requests';

    protected $fillable = [
        'offer_id',
        'counterparty_user_id',
        'initiator_user_id',
        'counterparty_id_number',
        'cp_source_account_id',
        'cp_dest_account_id',
        'sell_currency',
        'sell_amount',
        'buy_currency',
        'buy_amount',
        'exchange_rate',
        'status',
        'responded_at',
        'expires_at',
    ];

    protected $casts = [
        'sell_amount' => 'decimal:2',
        'buy_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'responded_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(P2POffer::class, 'offer_id');
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counterparty_user_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_user_id');
    }

    public function counterpartySourceAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class, 'cp_source_account_id');
    }

    public function counterpartyDestinationAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class, 'cp_dest_account_id');
    }

    public function escrowTransaction(): HasOne
    {
        return $this->hasOne(EscrowTransaction::class, 'exchange_request_id');
    }
}

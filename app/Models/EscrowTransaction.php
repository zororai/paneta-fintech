<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscrowTransaction extends Model
{
    protected $fillable = [
        'exchange_request_id',
        'initiator_user_id',
        'counterparty_user_id',
        'init_source_acct_id',
        'init_dest_acct_id',
        'cp_source_acct_id',
        'cp_dest_acct_id',
        'initiator_currency',
        'initiator_amount',
        'initiator_fee',
        'initiator_total',
        'counterparty_currency',
        'counterparty_amount',
        'counterparty_fee',
        'counterparty_total',
        'exchange_rate',
        'status',
        'initiator_confirmed',
        'counterparty_confirmed',
        'initiator_confirmed_at',
        'counterparty_confirmed_at',
        'precondition_checks',
        'preconditions_passed',
        'failure_reason',
        'expires_at',
    ];

    protected $casts = [
        'initiator_amount' => 'decimal:2',
        'initiator_fee' => 'decimal:2',
        'initiator_total' => 'decimal:2',
        'counterparty_amount' => 'decimal:2',
        'counterparty_fee' => 'decimal:2',
        'counterparty_total' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'initiator_confirmed' => 'boolean',
        'counterparty_confirmed' => 'boolean',
        'initiator_confirmed_at' => 'datetime',
        'counterparty_confirmed_at' => 'datetime',
        'precondition_checks' => 'array',
        'preconditions_passed' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function exchangeRequest(): BelongsTo
    {
        return $this->belongsTo(P2PExchangeRequest::class, 'exchange_request_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_user_id');
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counterparty_user_id');
    }

    public function atomicInstructions(): HasMany
    {
        return $this->hasMany(AtomicInstruction::class, 'escrow_transaction_id');
    }
}

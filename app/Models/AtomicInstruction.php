<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtomicInstruction extends Model
{
    protected $fillable = [
        'escrow_transaction_id',
        'user_id',
        'source_account_id',
        'destination_account_id',
        'instruction_type',
        'currency',
        'amount',
        'fee',
        'total_amount',
        'instruction_payload',
        'signed_hash',
        'status',
        'sent_at',
        'executed_at',
        'settled_at',
        'institution_response',
        'failure_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'instruction_payload' => 'array',
        'sent_at' => 'datetime',
        'executed_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function escrowTransaction(): BelongsTo
    {
        return $this->belongsTo(EscrowTransaction::class, 'escrow_transaction_id');
    }

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
}

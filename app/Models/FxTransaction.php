<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Institution;
use App\Models\LinkedAccount;

class FxTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider_id',
        'source_account_id',
        'destination_account_id',
        'source_currency',
        'destination_currency',
        'source_amount',
        'exchange_rate',
        'destination_amount',
        'paneta_fee',
        'provider_fee',
        'total_fees',
        'net_amount_debited',
        'settlement_preference',
        'status',
        'transaction_type',
        'metadata',
    ];

    protected $casts = [
        'source_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'destination_amount' => 'decimal:2',
        'paneta_fee' => 'decimal:2',
        'provider_fee' => 'decimal:2',
        'total_fees' => 'decimal:2',
        'net_amount_debited' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'provider_id');
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

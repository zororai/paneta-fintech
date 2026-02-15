<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TransactionIntent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'issuer_account_id',
        'acquirer_identifier',
        'amount',
        'currency',
        'status',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuerAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class, 'issuer_account_id');
    }

    public function paymentInstruction(): HasOne
    {
        return $this->hasOne(PaymentInstruction::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isExecuted(): bool
    {
        return $this->status === 'executed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public static function generateReference(): string
    {
        return 'TXN-' . strtoupper(bin2hex(random_bytes(8)));
    }
}

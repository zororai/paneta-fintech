<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentInstruction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_intent_id',
        'instruction_payload',
        'signed_hash',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'instruction_payload' => 'array',
        ];
    }

    public function transactionIntent(): BelongsTo
    {
        return $this->belongsTo(TransactionIntent::class);
    }

    public static function generateSignedHash(array $payload): string
    {
        return hash('sha256', json_encode($payload));
    }

    public function isGenerated(): bool
    {
        return $this->status === 'generated';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
}

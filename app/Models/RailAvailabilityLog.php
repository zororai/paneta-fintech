<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RailAvailabilityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_rail_id',
        'status',
        'response_time_ms',
        'error_message',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    public function rail(): BelongsTo
    {
        return $this->belongsTo(PaymentRail::class, 'payment_rail_id');
    }
}

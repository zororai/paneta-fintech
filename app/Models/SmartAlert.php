<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmartAlert extends Model
{
    protected $fillable = [
        'user_id',
        'currency_pair',
        'target_rate',
        'alert_type',
        'email_notifications',
        'sms_notifications',
        'push_notifications',
        'status',
        'triggered_at',
    ];

    protected $casts = [
        'target_rate' => 'decimal:6',
        'email_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
        'push_notifications' => 'boolean',
        'triggered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

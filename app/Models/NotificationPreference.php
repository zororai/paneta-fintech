<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'email_enabled',
        'sms_enabled',
        'push_enabled',
        'in_app_enabled',
        'email_types',
        'sms_types',
        'push_types',
        'preferred_language',
        'timezone',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'email_types' => 'array',
        'sms_types' => 'array',
        'push_types' => 'array',
        'quiet_hours_enabled' => 'boolean',
        'quiet_hours_start' => 'datetime:H:i',
        'quiet_hours_end' => 'datetime:H:i',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shouldSendEmail(string $notificationType): bool
    {
        if (!$this->email_enabled) {
            return false;
        }

        if ($this->email_types === null) {
            return true;
        }

        return in_array($notificationType, $this->email_types);
    }

    public function shouldSendSms(string $notificationType): bool
    {
        if (!$this->sms_enabled) {
            return false;
        }

        if ($this->sms_types === null) {
            return true;
        }

        return in_array($notificationType, $this->sms_types);
    }

    public function shouldSendPush(string $notificationType): bool
    {
        if (!$this->push_enabled) {
            return false;
        }

        if ($this->push_types === null) {
            return true;
        }

        return in_array($notificationType, $this->push_types);
    }

    public function isInQuietHours(): bool
    {
        if (!$this->quiet_hours_enabled || !$this->quiet_hours_start || !$this->quiet_hours_end) {
            return false;
        }

        $now = now()->setTimezone($this->timezone)->format('H:i');
        $start = $this->quiet_hours_start->format('H:i');
        $end = $this->quiet_hours_end->format('H:i');

        if ($start <= $end) {
            return $now >= $start && $now <= $end;
        }

        return $now >= $start || $now <= $end;
    }

    public static function forUser(User $user): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id],
            [
                'email_enabled' => true,
                'sms_enabled' => false,
                'push_enabled' => true,
                'in_app_enabled' => true,
                'preferred_language' => 'en',
                'timezone' => 'UTC',
            ]
        );
    }
}

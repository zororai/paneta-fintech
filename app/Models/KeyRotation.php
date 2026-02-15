<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeyRotation extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_DEPRECATED = 'deprecated';
    const STATUS_REVOKED = 'revoked';

    const KEY_TYPE_INSTRUCTION = 'instruction_secret';
    const KEY_TYPE_TOKEN_ENCRYPTION = 'token_encryption';
    const KEY_TYPE_HMAC = 'hmac_signing';

    protected $fillable = [
        'key_type',
        'version',
        'key_hash',
        'status',
        'activated_at',
        'deprecated_at',
        'revoked_at',
        'expires_at',
        'rotated_by',
        'rotation_reason',
        'metadata',
    ];

    protected $casts = [
        'version' => 'integer',
        'activated_at' => 'datetime',
        'deprecated_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function rotator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rotated_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isDeprecated(): bool
    {
        return $this->status === self::STATUS_DEPRECATED;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValidForSigning(): bool
    {
        return $this->isActive() && !$this->isExpired();
    }

    public function isValidForVerification(): bool
    {
        return !$this->isRevoked() && !$this->isExpired();
    }

    public function deprecate(): self
    {
        $this->update([
            'status' => self::STATUS_DEPRECATED,
            'deprecated_at' => now(),
        ]);
        return $this;
    }

    public function revoke(): self
    {
        $this->update([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);
        return $this;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForType($query, string $keyType)
    {
        return $query->where('key_type', $keyType);
    }

    public function scopeValidForVerification($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_DEPRECATED])
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public static function getCurrentVersion(string $keyType): ?self
    {
        return static::forType($keyType)
            ->active()
            ->orderByDesc('version')
            ->first();
    }

    public static function getNextVersion(string $keyType): int
    {
        $current = static::forType($keyType)->max('version');
        return ($current ?? 0) + 1;
    }
}

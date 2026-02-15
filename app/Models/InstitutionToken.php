<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class InstitutionToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'institution_id',
        'encrypted_token',
        'encrypted_refresh_token',
        'expires_at',
        'scopes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'scopes' => 'array',
    ];

    protected $hidden = [
        'encrypted_token',
        'encrypted_refresh_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function setTokenAttribute(string $value): void
    {
        $this->attributes['encrypted_token'] = Crypt::encryptString($value);
    }

    public function getDecryptedToken(): string
    {
        return Crypt::decryptString($this->encrypted_token);
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['encrypted_refresh_token'] = $value 
            ? Crypt::encryptString($value) 
            : null;
    }

    public function getDecryptedRefreshToken(): ?string
    {
        return $this->encrypted_refresh_token 
            ? Crypt::decryptString($this->encrypted_refresh_token) 
            : null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }
}

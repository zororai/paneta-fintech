<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo_url',
        'type',
        'consent_scopes',
        'capabilities',
        'country',
        'api_endpoint',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'consent_scopes' => 'array',
            'capabilities' => 'array',
        ];
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeFxProviders($query)
    {
        return $query->whereIn('type', ['fx_provider', 'remittance']);
    }

    public function scopeBrokers($query)
    {
        return $query->whereIn('type', ['broker', 'custodian']);
    }

    public function linkedAccounts(): HasMany
    {
        return $this->hasMany(LinkedAccount::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

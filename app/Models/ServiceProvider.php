<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class ServiceProvider extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $guard = 'service_provider';

    protected $fillable = [
        'provider_type',
        'company_name',
        'trading_name',
        'registration_number',
        'tax_id',
        'email',
        'password',
        'phone',
        'country',
        'address',
        'city',
        'postal_code',
        'regulatory_body',
        'license_number',
        'license_expiry',
        'regulatory_documents',
        'verification_status',
        'verification_notes',
        'verified_at',
        'verified_by',
        'business_description',
        'services_offered',
        'supported_currencies',
        'supported_countries',
        'minimum_transaction',
        'maximum_transaction',
        'commission_rate',
        'contact_person_name',
        'contact_person_email',
        'contact_person_phone',
        'contact_person_position',
        'bank_name',
        'bank_account_number',
        'bank_swift_code',
        'bank_iban',
        'is_active',
        'can_create_offers',
        'can_execute_trades',
        'auto_approve_trades',
        'rating',
        'total_trades',
        'total_volume',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'regulatory_documents' => 'array',
        'services_offered' => 'array',
        'supported_currencies' => 'array',
        'supported_countries' => 'array',
        'minimum_transaction' => 'decimal:2',
        'maximum_transaction' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'total_volume' => 'decimal:2',
        'is_active' => 'boolean',
        'can_create_offers' => 'boolean',
        'can_execute_trades' => 'boolean',
        'auto_approve_trades' => 'boolean',
        'email_verified_at' => 'datetime',
        'verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'license_expiry' => 'date',
    ];

    // Relationships
    public function fxOffers()
    {
        return $this->hasMany(FxOffer::class, 'provider_id');
    }

    public function fxTransactions()
    {
        return $this->hasMany(FxTransaction::class, 'provider_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // Scopes
    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFxProviders($query)
    {
        return $query->where('provider_type', 'fx_provider');
    }

    // Helper Methods
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function isUnderReview(): bool
    {
        return $this->verification_status === 'under_review';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }

    public function canTrade(): bool
    {
        return $this->is_active && $this->isVerified() && $this->can_execute_trades;
    }

    public function canCreateOffers(): bool
    {
        return $this->is_active && $this->isVerified() && $this->can_create_offers;
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    public function incrementTradeStats(float $volume): void
    {
        $this->increment('total_trades');
        $this->increment('total_volume', $volume);
    }
}

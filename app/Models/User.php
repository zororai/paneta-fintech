<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    const ROLE_USER = 'user';
    const ROLE_ADMIN = 'admin';
    const ROLE_REGULATOR = 'regulator';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'kyc_status',
        'risk_tier',
        'role',
        'account_type',
        'country_code',
        'phone',
        'country_of_origin',
        'city',
        'address',
        'profile_picture_path',
        'pin_hash',
        'date_of_birth',
        'company_name',
        'business_phone',
        'company_type',
        'business_sector',
        'services_offered',
        'registration_number',
        'physical_address',
        'website',
        'tax_id',
        'business_email',
        'company_logo_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'pin_hash',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'date_of_birth' => 'date',
        ];
    }

    public function linkedAccounts(): HasMany
    {
        return $this->hasMany(LinkedAccount::class);
    }

    public function transactionIntents(): HasMany
    {
        return $this->hasMany(TransactionIntent::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function aggregatedAccounts(): HasMany
    {
        return $this->hasMany(AggregatedAccount::class);
    }

    public function institutionTokens(): HasMany
    {
        return $this->hasMany(InstitutionToken::class);
    }

    public function crossBorderTransactions(): HasMany
    {
        return $this->hasMany(CrossBorderTransactionIntent::class);
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function fxOffers(): HasMany
    {
        return $this->hasMany(FxOffer::class);
    }

    public function merchant(): HasOne
    {
        return $this->hasOne(Merchant::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->active();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function wealthPortfolio(): HasOne
    {
        return $this->hasOne(WealthPortfolio::class);
    }

    public function securityLogs(): HasMany
    {
        return $this->hasMany(SecurityLog::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isRegulator(): bool
    {
        return $this->role === self::ROLE_REGULATOR;
    }

    public function isPrivileged(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_REGULATOR]);
    }

    public function canModifyData(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isKycVerified(): bool
    {
        return $this->kyc_status === 'verified';
    }

    public function isMerchant(): bool
    {
        return $this->merchant()->exists();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()->active()->exists();
    }

    public function isPersonalAccount(): bool
    {
        return $this->account_type === 'personal';
    }

    public function isBusinessAccount(): bool
    {
        return $this->account_type === 'business';
    }

    /**
     * Services restricted for personal accounts:
     * - Merchant SoftPOS
     * - FX Dealership rights
     * - Batch Payments
     * - Multiple accounts under same account
     */
    public function canAccessMerchantSoftPOS(): bool
    {
        return $this->isBusinessAccount();
    }

    public function canAccessFXDealership(): bool
    {
        return $this->isBusinessAccount();
    }

    public function canAccessBatchPayments(): bool
    {
        return $this->isBusinessAccount();
    }

    public function canHaveMultipleAccounts(): bool
    {
        return $this->isBusinessAccount();
    }

    /**
     * Get user entitlements for frontend gating
     */
    public function getEntitlements(): array
    {
        return [
            'merchant_softpos' => $this->canAccessMerchantSoftPOS(),
            'fx_dealership' => $this->canAccessFXDealership(),
            'batch_payments' => $this->canAccessBatchPayments(),
            'multiple_accounts' => $this->canHaveMultipleAccounts(),
        ];
    }
}

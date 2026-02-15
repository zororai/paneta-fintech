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
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
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
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FXCorridorPolicyEngine
{
    const CACHE_TTL = 3600; // 1 hour

    protected array $corridorRestrictions = [
        // Source -> Destination restrictions
        'USD' => [
            'restricted' => ['KPW', 'IRR', 'SYP', 'CUP'],
            'requires_enhanced_kyc' => ['RUB', 'BYN'],
            'max_single_amount' => 50000,
            'max_daily_amount' => 100000,
        ],
        'EUR' => [
            'restricted' => ['KPW', 'IRR', 'SYP', 'CUP'],
            'requires_enhanced_kyc' => ['RUB', 'BYN'],
            'max_single_amount' => 50000,
            'max_daily_amount' => 100000,
        ],
        'GBP' => [
            'restricted' => ['KPW', 'IRR', 'SYP', 'CUP'],
            'requires_enhanced_kyc' => ['RUB', 'BYN'],
            'max_single_amount' => 40000,
            'max_daily_amount' => 80000,
        ],
        'ZAR' => [
            'restricted' => ['KPW', 'IRR', 'SYP'],
            'requires_enhanced_kyc' => [],
            'max_single_amount' => 500000,
            'max_daily_amount' => 1000000,
        ],
    ];

    protected array $countryRestrictions = [
        'sanctioned' => ['KP', 'IR', 'SY', 'CU'],
        'high_risk' => ['AF', 'YE', 'LY', 'SO', 'SD'],
        'requires_enhanced_due_diligence' => ['RU', 'BY', 'VE', 'MM'],
    ];

    public function validateCorridor(
        string $sourceCurrency,
        string $destinationCurrency,
        string $sourceCountry,
        string $destinationCountry,
        float $amount,
        User $user
    ): array {
        $violations = [];

        // Check currency restrictions
        $currencyCheck = $this->checkCurrencyRestrictions($sourceCurrency, $destinationCurrency);
        if (!$currencyCheck['allowed']) {
            $violations[] = $currencyCheck;
        }

        // Check country restrictions
        $countryCheck = $this->checkCountryRestrictions($sourceCountry, $destinationCountry);
        if (!$countryCheck['allowed']) {
            $violations[] = $countryCheck;
        }

        // Check amount limits
        $amountCheck = $this->checkAmountLimits($sourceCurrency, $amount, $user);
        if (!$amountCheck['allowed']) {
            $violations[] = $amountCheck;
        }

        // Check KYC requirements
        $kycCheck = $this->checkKycRequirements($sourceCurrency, $destinationCurrency, $user);
        if (!$kycCheck['allowed']) {
            $violations[] = $kycCheck;
        }

        $isAllowed = empty($violations);

        if (!$isAllowed) {
            Log::warning('FX corridor policy violation', [
                'user_id' => $user->id,
                'source_currency' => $sourceCurrency,
                'destination_currency' => $destinationCurrency,
                'amount' => $amount,
                'violations' => $violations,
            ]);
        }

        return [
            'allowed' => $isAllowed,
            'violations' => $violations,
            'requires_review' => $this->requiresManualReview($sourceCurrency, $destinationCurrency, $sourceCountry, $destinationCountry, $amount),
        ];
    }

    public function checkCurrencyRestrictions(string $sourceCurrency, string $destinationCurrency): array
    {
        $policy = $this->corridorRestrictions[$sourceCurrency] ?? null;

        if (!$policy) {
            return ['allowed' => true];
        }

        if (in_array($destinationCurrency, $policy['restricted'] ?? [])) {
            return [
                'allowed' => false,
                'type' => 'currency_restricted',
                'message' => "Currency corridor {$sourceCurrency} to {$destinationCurrency} is restricted",
            ];
        }

        return ['allowed' => true];
    }

    public function checkCountryRestrictions(string $sourceCountry, string $destinationCountry): array
    {
        if (in_array($destinationCountry, $this->countryRestrictions['sanctioned'])) {
            return [
                'allowed' => false,
                'type' => 'country_sanctioned',
                'message' => "Transfers to {$destinationCountry} are prohibited due to sanctions",
            ];
        }

        if (in_array($sourceCountry, $this->countryRestrictions['sanctioned'])) {
            return [
                'allowed' => false,
                'type' => 'country_sanctioned',
                'message' => "Transfers from {$sourceCountry} are prohibited due to sanctions",
            ];
        }

        return ['allowed' => true];
    }

    public function checkAmountLimits(string $currency, float $amount, User $user): array
    {
        $policy = $this->corridorRestrictions[$currency] ?? null;

        if (!$policy) {
            return ['allowed' => true];
        }

        $maxSingle = $policy['max_single_amount'] ?? PHP_FLOAT_MAX;
        if ($amount > $maxSingle) {
            return [
                'allowed' => false,
                'type' => 'amount_exceeded',
                'message' => "Single transaction limit of {$maxSingle} {$currency} exceeded",
                'limit' => $maxSingle,
            ];
        }

        // Check daily limit
        $dailyTotal = $this->getUserDailyVolume($user, $currency);
        $maxDaily = $policy['max_daily_amount'] ?? PHP_FLOAT_MAX;

        if (($dailyTotal + $amount) > $maxDaily) {
            return [
                'allowed' => false,
                'type' => 'daily_limit_exceeded',
                'message' => "Daily limit of {$maxDaily} {$currency} would be exceeded",
                'limit' => $maxDaily,
                'current_usage' => $dailyTotal,
                'remaining' => max(0, $maxDaily - $dailyTotal),
            ];
        }

        return ['allowed' => true];
    }

    public function checkKycRequirements(string $sourceCurrency, string $destinationCurrency, User $user): array
    {
        $policy = $this->corridorRestrictions[$sourceCurrency] ?? null;

        if (!$policy) {
            return ['allowed' => true];
        }

        if (in_array($destinationCurrency, $policy['requires_enhanced_kyc'] ?? [])) {
            if ($user->kyc_status !== 'verified' || ($user->risk_tier ?? 'standard') === 'high') {
                return [
                    'allowed' => false,
                    'type' => 'enhanced_kyc_required',
                    'message' => "Enhanced KYC verification required for {$sourceCurrency} to {$destinationCurrency}",
                ];
            }
        }

        return ['allowed' => true];
    }

    public function requiresManualReview(
        string $sourceCurrency,
        string $destinationCurrency,
        string $sourceCountry,
        string $destinationCountry,
        float $amount
    ): bool {
        // High-value transactions
        if ($amount > 25000) {
            return true;
        }

        // High-risk countries
        if (in_array($destinationCountry, $this->countryRestrictions['high_risk'])) {
            return true;
        }

        // EDD countries
        if (in_array($destinationCountry, $this->countryRestrictions['requires_enhanced_due_diligence'])) {
            return true;
        }

        return false;
    }

    public function getCorridorPolicy(string $sourceCurrency): ?array
    {
        return $this->corridorRestrictions[$sourceCurrency] ?? null;
    }

    public function getAllowedDestinations(string $sourceCurrency): array
    {
        $policy = $this->corridorRestrictions[$sourceCurrency] ?? null;
        $allCurrencies = ['USD', 'EUR', 'GBP', 'ZAR', 'NGN', 'KES', 'GHS', 'BWP', 'MZN', 'ZMW'];
        
        if (!$policy) {
            return $allCurrencies;
        }

        return array_diff($allCurrencies, $policy['restricted'] ?? []);
    }

    public function getCorridorLimits(string $sourceCurrency, User $user): array
    {
        $policy = $this->corridorRestrictions[$sourceCurrency] ?? [
            'max_single_amount' => 50000,
            'max_daily_amount' => 100000,
        ];

        $dailyUsed = $this->getUserDailyVolume($user, $sourceCurrency);

        return [
            'currency' => $sourceCurrency,
            'max_single_transaction' => $policy['max_single_amount'],
            'max_daily_volume' => $policy['max_daily_amount'],
            'daily_used' => $dailyUsed,
            'daily_remaining' => max(0, $policy['max_daily_amount'] - $dailyUsed),
        ];
    }

    protected function getUserDailyVolume(User $user, string $currency): float
    {
        $cacheKey = "user_daily_fx_volume:{$user->id}:{$currency}:" . now()->format('Y-m-d');

        return Cache::remember($cacheKey, 300, function () use ($user, $currency) {
            return $user->transactionIntents()
                ->whereDate('created_at', today())
                ->where('currency', $currency)
                ->whereIn('status', ['pending', 'confirmed', 'executed'])
                ->sum('amount');
        });
    }

    public function isHighRiskCorridor(string $sourceCurrency, string $destinationCurrency, string $destinationCountry): bool
    {
        $policy = $this->corridorRestrictions[$sourceCurrency] ?? null;

        if ($policy && in_array($destinationCurrency, $policy['requires_enhanced_kyc'] ?? [])) {
            return true;
        }

        if (in_array($destinationCountry, $this->countryRestrictions['high_risk'])) {
            return true;
        }

        return false;
    }
}

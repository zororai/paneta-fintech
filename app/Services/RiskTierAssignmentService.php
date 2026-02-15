<?php

namespace App\Services;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\Log;

class RiskTierAssignmentService
{
    const TIER_LOW = 'low';
    const TIER_STANDARD = 'standard';
    const TIER_ELEVATED = 'elevated';
    const TIER_HIGH = 'high';

    const TIER_LIMITS = [
        self::TIER_LOW => [
            'daily_limit' => 50000,
            'single_limit' => 25000,
            'monthly_limit' => 500000,
            'cross_border_allowed' => true,
            'p2p_fx_allowed' => true,
            'review_frequency_days' => 365,
        ],
        self::TIER_STANDARD => [
            'daily_limit' => 10000,
            'single_limit' => 5000,
            'monthly_limit' => 100000,
            'cross_border_allowed' => true,
            'p2p_fx_allowed' => true,
            'review_frequency_days' => 180,
        ],
        self::TIER_ELEVATED => [
            'daily_limit' => 5000,
            'single_limit' => 2000,
            'monthly_limit' => 25000,
            'cross_border_allowed' => true,
            'p2p_fx_allowed' => false,
            'review_frequency_days' => 90,
        ],
        self::TIER_HIGH => [
            'daily_limit' => 1000,
            'single_limit' => 500,
            'monthly_limit' => 5000,
            'cross_border_allowed' => false,
            'p2p_fx_allowed' => false,
            'review_frequency_days' => 30,
        ],
    ];

    protected AuditService $audit;

    public function __construct(AuditService $audit)
    {
        $this->audit = $audit;
    }

    public function assessRisk(User $user): array
    {
        $factors = $this->collectRiskFactors($user);
        $score = $this->calculateRiskScore($factors);
        $tier = $this->scoreToTier($score);

        return [
            'user_id' => $user->id,
            'risk_score' => $score,
            'risk_tier' => $tier,
            'factors' => $factors,
            'limits' => self::TIER_LIMITS[$tier],
            'assessed_at' => now()->toIso8601String(),
        ];
    }

    public function assignTier(User $user, string $tier = null, string $reason = null): bool
    {
        if ($tier === null) {
            $assessment = $this->assessRisk($user);
            $tier = $assessment['risk_tier'];
        }

        if (!isset(self::TIER_LIMITS[$tier])) {
            throw new \InvalidArgumentException("Invalid risk tier: {$tier}");
        }

        $previousTier = $user->risk_tier;
        
        $user->update([
            'risk_tier' => $tier,
            'next_reverification_at' => now()->addDays(self::TIER_LIMITS[$tier]['review_frequency_days']),
        ]);

        $this->audit->log(
            $user->id,
            'risk_tier_assigned',
            'user',
            $user->id,
            [
                'previous_tier' => $previousTier,
                'new_tier' => $tier,
                'reason' => $reason ?? 'Automated assessment',
            ]
        );

        Log::info('Risk tier assigned', [
            'user_id' => $user->id,
            'previous_tier' => $previousTier,
            'new_tier' => $tier,
        ]);

        return true;
    }

    public function getTierLimits(string $tier): array
    {
        return self::TIER_LIMITS[$tier] ?? self::TIER_LIMITS[self::TIER_STANDARD];
    }

    public function getUserLimits(User $user): array
    {
        $tier = $user->risk_tier ?? self::TIER_STANDARD;
        return $this->getTierLimits($tier);
    }

    public function canPerformAction(User $user, string $action): bool
    {
        $limits = $this->getUserLimits($user);

        return match ($action) {
            'cross_border' => $limits['cross_border_allowed'],
            'p2p_fx' => $limits['p2p_fx_allowed'],
            default => true,
        };
    }

    public function checkTransactionAllowed(User $user, float $amount, string $type = 'standard'): array
    {
        $limits = $this->getUserLimits($user);

        // Check single transaction limit
        if ($amount > $limits['single_limit']) {
            return [
                'allowed' => false,
                'reason' => 'single_limit_exceeded',
                'limit' => $limits['single_limit'],
                'requested' => $amount,
            ];
        }

        // Check daily limit
        $dailyTotal = $this->getUserDailyTotal($user);
        if (($dailyTotal + $amount) > $limits['daily_limit']) {
            return [
                'allowed' => false,
                'reason' => 'daily_limit_exceeded',
                'limit' => $limits['daily_limit'],
                'used' => $dailyTotal,
                'remaining' => max(0, $limits['daily_limit'] - $dailyTotal),
            ];
        }

        // Check monthly limit
        $monthlyTotal = $this->getUserMonthlyTotal($user);
        if (($monthlyTotal + $amount) > $limits['monthly_limit']) {
            return [
                'allowed' => false,
                'reason' => 'monthly_limit_exceeded',
                'limit' => $limits['monthly_limit'],
                'used' => $monthlyTotal,
                'remaining' => max(0, $limits['monthly_limit'] - $monthlyTotal),
            ];
        }

        // Check action-specific restrictions
        if ($type === 'cross_border' && !$limits['cross_border_allowed']) {
            return [
                'allowed' => false,
                'reason' => 'cross_border_not_allowed',
                'tier' => $user->risk_tier,
            ];
        }

        if ($type === 'p2p_fx' && !$limits['p2p_fx_allowed']) {
            return [
                'allowed' => false,
                'reason' => 'p2p_fx_not_allowed',
                'tier' => $user->risk_tier,
            ];
        }

        return [
            'allowed' => true,
            'limits' => $limits,
            'daily_remaining' => $limits['daily_limit'] - $dailyTotal,
            'monthly_remaining' => $limits['monthly_limit'] - $monthlyTotal,
        ];
    }

    protected function collectRiskFactors(User $user): array
    {
        $factors = [];

        // KYC status
        $factors['kyc_verified'] = $user->kyc_status === 'verified';
        $factors['kyc_score'] = $factors['kyc_verified'] ? 0 : 30;

        // Account age
        $accountAgeDays = $user->created_at->diffInDays(now());
        $factors['account_age_days'] = $accountAgeDays;
        $factors['account_age_score'] = $accountAgeDays < 30 ? 20 : ($accountAgeDays < 90 ? 10 : 0);

        // Email verification
        $factors['email_verified'] = $user->email_verified_at !== null;
        $factors['email_score'] = $factors['email_verified'] ? 0 : 15;

        // Transaction history
        $executedTransactions = $user->transactionIntents()->where('status', 'executed')->count();
        $failedTransactions = $user->transactionIntents()->where('status', 'failed')->count();
        $factors['transaction_count'] = $executedTransactions;
        $factors['failed_transaction_count'] = $failedTransactions;
        $factors['transaction_score'] = $failedTransactions > 3 ? 25 : ($executedTransactions < 5 ? 10 : 0);

        // Linked accounts
        $linkedAccounts = $user->linkedAccounts()->count();
        $factors['linked_accounts'] = $linkedAccounts;
        $factors['linked_accounts_score'] = $linkedAccounts < 1 ? 15 : 0;

        // Country risk (placeholder - would integrate with country risk database)
        $factors['country'] = $user->country ?? 'US';
        $factors['country_score'] = 0; // Default no additional risk

        return $factors;
    }

    protected function calculateRiskScore(array $factors): int
    {
        $score = 0;
        $score += $factors['kyc_score'] ?? 0;
        $score += $factors['account_age_score'] ?? 0;
        $score += $factors['email_score'] ?? 0;
        $score += $factors['transaction_score'] ?? 0;
        $score += $factors['linked_accounts_score'] ?? 0;
        $score += $factors['country_score'] ?? 0;

        return min(100, max(0, $score));
    }

    protected function scoreToTier(int $score): string
    {
        if ($score <= 10) {
            return self::TIER_LOW;
        }
        if ($score <= 30) {
            return self::TIER_STANDARD;
        }
        if ($score <= 60) {
            return self::TIER_ELEVATED;
        }
        return self::TIER_HIGH;
    }

    protected function getUserDailyTotal(User $user): float
    {
        return $user->transactionIntents()
            ->whereDate('created_at', today())
            ->whereIn('status', ['pending', 'confirmed', 'executed'])
            ->sum('amount');
    }

    protected function getUserMonthlyTotal(User $user): float
    {
        return $user->transactionIntents()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereIn('status', ['pending', 'confirmed', 'executed'])
            ->sum('amount');
    }

    public function scheduleReverification(): array
    {
        $usersNeedingReview = User::whereNotNull('next_reverification_at')
            ->where('next_reverification_at', '<=', now())
            ->get();

        $results = [];
        foreach ($usersNeedingReview as $user) {
            $assessment = $this->assessRisk($user);
            $this->assignTier($user, $assessment['risk_tier'], 'Scheduled reverification');
            $results[] = [
                'user_id' => $user->id,
                'new_tier' => $assessment['risk_tier'],
            ];
        }

        return [
            'processed' => count($results),
            'results' => $results,
        ];
    }
}

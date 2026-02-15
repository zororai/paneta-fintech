<?php

namespace App\Services;

use App\Models\User;
use App\Models\Subscription;

class EntitlementEngine
{
    public function __construct(
        protected SubscriptionEngine $subscriptionEngine
    ) {}

    const FREE_TIER_LIMITS = [
        'max_linked_accounts' => 2,
        'max_daily_transactions' => 5,
        'max_transaction_amount' => 1000,
        'cross_border_enabled' => false,
        'p2p_fx_enabled' => false,
        'wealth_analytics_enabled' => false,
        'priority_support' => false,
    ];

    const FEATURE_MAP = [
        'basic' => [
            'max_linked_accounts' => 5,
            'max_daily_transactions' => 20,
            'max_transaction_amount' => 10000,
            'cross_border_enabled' => true,
            'p2p_fx_enabled' => false,
            'wealth_analytics_enabled' => false,
            'priority_support' => false,
        ],
        'premium' => [
            'max_linked_accounts' => 20,
            'max_daily_transactions' => 100,
            'max_transaction_amount' => 50000,
            'cross_border_enabled' => true,
            'p2p_fx_enabled' => true,
            'wealth_analytics_enabled' => true,
            'priority_support' => false,
        ],
        'enterprise' => [
            'max_linked_accounts' => -1,
            'max_daily_transactions' => -1,
            'max_transaction_amount' => -1,
            'cross_border_enabled' => true,
            'p2p_fx_enabled' => true,
            'wealth_analytics_enabled' => true,
            'priority_support' => true,
        ],
    ];

    public function getUserEntitlements(User $user): array
    {
        $subscription = $this->subscriptionEngine->getActiveSubscription($user);

        if (!$subscription) {
            return self::FREE_TIER_LIMITS;
        }

        $planCode = $subscription->plan->code;
        $planLimits = $subscription->plan->limits ?? [];
        $planFeatures = $subscription->plan->features ?? [];

        $baseEntitlements = self::FEATURE_MAP[$planCode] ?? self::FREE_TIER_LIMITS;

        return array_merge($baseEntitlements, $planLimits);
    }

    public function canPerformAction(User $user, string $action, array $context = []): EntitlementCheckResult
    {
        $entitlements = $this->getUserEntitlements($user);

        return match ($action) {
            'link_account' => $this->checkLinkedAccountLimit($user, $entitlements),
            'create_transaction' => $this->checkTransactionLimits($user, $entitlements, $context),
            'cross_border' => $this->checkFeatureEnabled($entitlements, 'cross_border_enabled'),
            'p2p_fx' => $this->checkFeatureEnabled($entitlements, 'p2p_fx_enabled'),
            'wealth_analytics' => $this->checkFeatureEnabled($entitlements, 'wealth_analytics_enabled'),
            default => new EntitlementCheckResult(allowed: true),
        };
    }

    protected function checkLinkedAccountLimit(User $user, array $entitlements): EntitlementCheckResult
    {
        $limit = $entitlements['max_linked_accounts'] ?? 2;
        
        if ($limit === -1) {
            return new EntitlementCheckResult(allowed: true);
        }

        $currentCount = $user->linkedAccounts()->active()->count();

        if ($currentCount >= $limit) {
            return new EntitlementCheckResult(
                allowed: false,
                reason: "Maximum linked accounts limit reached ({$limit})",
                upgradeRequired: true
            );
        }

        return new EntitlementCheckResult(
            allowed: true,
            remaining: $limit - $currentCount
        );
    }

    protected function checkTransactionLimits(User $user, array $entitlements, array $context): EntitlementCheckResult
    {
        $dailyLimit = $entitlements['max_daily_transactions'] ?? 5;
        $amountLimit = $entitlements['max_transaction_amount'] ?? 1000;

        if ($dailyLimit !== -1) {
            $todayCount = $user->transactionIntents()
                ->whereDate('created_at', today())
                ->count();

            if ($todayCount >= $dailyLimit) {
                return new EntitlementCheckResult(
                    allowed: false,
                    reason: "Daily transaction limit reached ({$dailyLimit})",
                    upgradeRequired: true
                );
            }
        }

        if ($amountLimit !== -1 && isset($context['amount'])) {
            if ($context['amount'] > $amountLimit) {
                return new EntitlementCheckResult(
                    allowed: false,
                    reason: "Transaction amount exceeds limit (\${$amountLimit})",
                    upgradeRequired: true
                );
            }
        }

        return new EntitlementCheckResult(allowed: true);
    }

    protected function checkFeatureEnabled(array $entitlements, string $feature): EntitlementCheckResult
    {
        $enabled = $entitlements[$feature] ?? false;

        if (!$enabled) {
            return new EntitlementCheckResult(
                allowed: false,
                reason: 'This feature requires a subscription upgrade',
                upgradeRequired: true
            );
        }

        return new EntitlementCheckResult(allowed: true);
    }

    public function getUpgradeRecommendation(User $user): ?array
    {
        $subscription = $this->subscriptionEngine->getActiveSubscription($user);
        $currentTier = $subscription?->plan->code ?? 'free';

        $upgradeMap = [
            'free' => 'basic',
            'basic' => 'premium',
            'premium' => 'enterprise',
            'enterprise' => null,
        ];

        $recommendedPlan = $upgradeMap[$currentTier] ?? null;

        if (!$recommendedPlan) {
            return null;
        }

        return [
            'current_plan' => $currentTier,
            'recommended_plan' => $recommendedPlan,
            'benefits' => $this->getUpgradeBenefits($currentTier, $recommendedPlan),
        ];
    }

    protected function getUpgradeBenefits(string $from, string $to): array
    {
        $fromLimits = self::FEATURE_MAP[$from] ?? self::FREE_TIER_LIMITS;
        $toLimits = self::FEATURE_MAP[$to] ?? [];

        $benefits = [];

        foreach ($toLimits as $key => $value) {
            $oldValue = $fromLimits[$key] ?? null;
            if ($value !== $oldValue) {
                $benefits[$key] = [
                    'from' => $oldValue,
                    'to' => $value,
                ];
            }
        }

        return $benefits;
    }
}

class EntitlementCheckResult
{
    public function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public bool $upgradeRequired = false,
        public int $remaining = -1
    ) {}
}

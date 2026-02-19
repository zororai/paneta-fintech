<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SubscriptionEntitlementEnforcer
 * 
 * Enforces subscription-based feature access:
 * - Feature access validation
 * - Tier limit enforcement
 * - API endpoint gating
 * - Usage tracking
 */
class SubscriptionEntitlementEnforcer
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Validate user has access to a specific feature
     */
    public function validateFeatureAccess(User $user, string $feature): bool
    {
        $entitlements = $this->getUserEntitlements($user);

        if (!isset($entitlements['features']) || !in_array($feature, $entitlements['features'])) {
            $this->auditService->log(
                'feature_access_denied',
                'subscription',
                $user->subscription?->id,
                $user,
                [
                    'feature' => $feature,
                    'user_tier' => $entitlements['tier'] ?? 'none',
                ]
            );

            return false;
        }

        return true;
    }

    /**
     * Enforce tier-based limits
     */
    public function enforceTierLimits(User $user, string $limitType, int $currentUsage): array
    {
        $entitlements = $this->getUserEntitlements($user);
        $limits = $entitlements['limits'] ?? [];

        if (!isset($limits[$limitType])) {
            return [
                'allowed' => true,
                'limit' => null,
                'current_usage' => $currentUsage,
            ];
        }

        $limit = $limits[$limitType];
        $allowed = $currentUsage < $limit;

        if (!$allowed) {
            $this->auditService->log(
                'tier_limit_exceeded',
                'subscription',
                $user->subscription?->id,
                $user,
                [
                    'limit_type' => $limitType,
                    'limit' => $limit,
                    'current_usage' => $currentUsage,
                ]
            );
        }

        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'current_usage' => $currentUsage,
            'remaining' => max(0, $limit - $currentUsage),
        ];
    }

    /**
     * Gate API endpoints based on subscription
     */
    public function gateAPIEndpoints(User $user, string $endpoint): bool
    {
        $entitlements = $this->getUserEntitlements($user);
        $allowedEndpoints = $entitlements['api_endpoints'] ?? [];

        if (in_array('*', $allowedEndpoints)) {
            return true;
        }

        foreach ($allowedEndpoints as $pattern) {
            if ($this->endpointMatchesPattern($endpoint, $pattern)) {
                return true;
            }
        }

        $this->auditService->log(
            'api_endpoint_blocked',
            'subscription',
            $user->subscription?->id,
            $user,
            [
                'endpoint' => $endpoint,
                'user_tier' => $entitlements['tier'] ?? 'none',
            ]
        );

        return false;
    }

    /**
     * Get user's current entitlements
     */
    public function getUserEntitlements(User $user): array
    {
        $cacheKey = "user_entitlements:{$user->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($user) {
            $subscription = $user->subscription;

            if (!$subscription || $subscription->status !== 'active') {
                return $this->getFreeTierEntitlements();
            }

            $plan = $subscription->plan;

            if (!$plan) {
                return $this->getFreeTierEntitlements();
            }

            return [
                'tier' => $plan->tier,
                'plan_name' => $plan->name,
                'features' => $plan->features ?? [],
                'limits' => $plan->limits ?? [],
                'api_endpoints' => $plan->api_endpoints ?? ['*'],
                'expires_at' => $subscription->expires_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Check if user can perform an action
     */
    public function canPerformAction(User $user, string $action): array
    {
        $entitlements = $this->getUserEntitlements($user);

        $actionRequirements = $this->getActionRequirements($action);

        if (empty($actionRequirements)) {
            return ['allowed' => true, 'reason' => 'No restrictions'];
        }

        if (isset($actionRequirements['min_tier'])) {
            $tierHierarchy = ['free' => 0, 'basic' => 1, 'premium' => 2, 'enterprise' => 3];
            $userTierLevel = $tierHierarchy[$entitlements['tier']] ?? 0;
            $requiredLevel = $tierHierarchy[$actionRequirements['min_tier']] ?? 0;

            if ($userTierLevel < $requiredLevel) {
                return [
                    'allowed' => false,
                    'reason' => "Requires {$actionRequirements['min_tier']} tier or higher",
                    'upgrade_required' => true,
                ];
            }
        }

        if (isset($actionRequirements['feature'])) {
            if (!$this->validateFeatureAccess($user, $actionRequirements['feature'])) {
                return [
                    'allowed' => false,
                    'reason' => "Feature '{$actionRequirements['feature']}' not available in your plan",
                    'upgrade_required' => true,
                ];
            }
        }

        return ['allowed' => true, 'reason' => 'All requirements met'];
    }

    /**
     * Track usage for a specific limit type
     */
    public function trackUsage(User $user, string $limitType, int $increment = 1): array
    {
        $cacheKey = "usage:{$user->id}:{$limitType}:" . now()->format('Y-m');
        
        $currentUsage = Cache::get($cacheKey, 0);
        $newUsage = $currentUsage + $increment;

        Cache::put($cacheKey, $newUsage, now()->endOfMonth());

        return $this->enforceTierLimits($user, $limitType, $newUsage);
    }

    /**
     * Reset usage counters (typically monthly)
     */
    public function resetUsageCounters(User $user, ?string $limitType = null): void
    {
        if ($limitType) {
            $cacheKey = "usage:{$user->id}:{$limitType}:" . now()->format('Y-m');
            Cache::forget($cacheKey);
        } else {
            $limitTypes = ['transactions', 'fx_quotes', 'api_calls', 'linked_accounts'];
            foreach ($limitTypes as $type) {
                $cacheKey = "usage:{$user->id}:{$type}:" . now()->format('Y-m');
                Cache::forget($cacheKey);
            }
        }

        Cache::forget("user_entitlements:{$user->id}");
    }

    /**
     * Get subscription status for user
     */
    public function getSubscriptionStatus(User $user): array
    {
        $subscription = $user->subscription;

        if (!$subscription) {
            return [
                'has_subscription' => false,
                'tier' => 'free',
                'status' => 'none',
            ];
        }

        return [
            'has_subscription' => true,
            'tier' => $subscription->plan?->tier ?? 'free',
            'plan_name' => $subscription->plan?->name,
            'status' => $subscription->status,
            'expires_at' => $subscription->expires_at?->toIso8601String(),
            'auto_renew' => $subscription->auto_renew ?? false,
            'days_remaining' => $subscription->expires_at 
                ? now()->diffInDays($subscription->expires_at, false) 
                : null,
        ];
    }

    /**
     * Get free tier entitlements
     */
    private function getFreeTierEntitlements(): array
    {
        return [
            'tier' => 'free',
            'plan_name' => 'Free',
            'features' => [
                'dashboard',
                'linked_accounts',
                'basic_transactions',
            ],
            'limits' => [
                'transactions' => 10,
                'linked_accounts' => 2,
                'fx_quotes' => 5,
                'api_calls' => 100,
            ],
            'api_endpoints' => [
                '/paneta/dashboard',
                '/paneta/accounts',
                '/paneta/transactions',
            ],
        ];
    }

    /**
     * Get action requirements
     */
    private function getActionRequirements(string $action): array
    {
        $requirements = [
            'send_money' => ['feature' => 'transactions'],
            'fx_quote' => ['feature' => 'currency_exchange'],
            'wealth_analytics' => ['feature' => 'wealth_management', 'min_tier' => 'premium'],
            'api_access' => ['min_tier' => 'basic'],
            'bulk_transactions' => ['min_tier' => 'enterprise'],
            'priority_support' => ['min_tier' => 'premium'],
        ];

        return $requirements[$action] ?? [];
    }

    /**
     * Check if endpoint matches pattern
     */
    private function endpointMatchesPattern(string $endpoint, string $pattern): bool
    {
        $pattern = str_replace('*', '.*', $pattern);
        return (bool) preg_match("#^{$pattern}$#", $endpoint);
    }
}

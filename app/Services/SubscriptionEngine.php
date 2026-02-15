<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;

class SubscriptionEngine
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function subscribe(
        User $user,
        SubscriptionPlan $plan,
        string $billingCycle = 'monthly'
    ): SubscriptionResult {
        $existingActive = Subscription::where('user_id', $user->id)
            ->active()
            ->first();

        if ($existingActive) {
            return new SubscriptionResult(
                success: false,
                error: 'User already has an active subscription'
            );
        }

        $duration = $billingCycle === 'annual' ? 365 : 30;

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => $billingCycle,
            'started_at' => now(),
            'expires_at' => now()->addDays($duration),
        ]);

        $this->auditService->log(
            $user->id,
            'subscription_created',
            'Subscription',
            $subscription->id,
            [
                'plan' => $plan->code,
                'billing_cycle' => $billingCycle,
            ]
        );

        return new SubscriptionResult(
            success: true,
            subscription: $subscription
        );
    }

    public function cancel(Subscription $subscription, ?string $reason = null): bool
    {
        if ($subscription->status === 'cancelled') {
            return false;
        }

        $subscription->cancel($reason);

        $this->auditService->log(
            $subscription->user_id,
            'subscription_cancelled',
            'Subscription',
            $subscription->id,
            ['reason' => $reason]
        );

        return true;
    }

    public function renew(Subscription $subscription): SubscriptionResult
    {
        if ($subscription->status !== 'active' && $subscription->status !== 'past_due') {
            return new SubscriptionResult(
                success: false,
                error: 'Subscription cannot be renewed'
            );
        }

        $subscription->renew();

        $this->auditService->log(
            $subscription->user_id,
            'subscription_renewed',
            'Subscription',
            $subscription->id,
            []
        );

        return new SubscriptionResult(
            success: true,
            subscription: $subscription->fresh()
        );
    }

    public function upgrade(User $user, SubscriptionPlan $newPlan): SubscriptionResult
    {
        $current = Subscription::where('user_id', $user->id)->active()->first();

        if (!$current) {
            return $this->subscribe($user, $newPlan, 'monthly');
        }

        if ($newPlan->tier <= $current->plan->tier) {
            return new SubscriptionResult(
                success: false,
                error: 'New plan must be higher tier than current plan'
            );
        }

        $current->update(['plan_id' => $newPlan->id]);

        $this->auditService->log(
            $user->id,
            'subscription_upgraded',
            'Subscription',
            $current->id,
            ['new_plan' => $newPlan->code]
        );

        return new SubscriptionResult(
            success: true,
            subscription: $current->fresh()
        );
    }

    public function getActiveSubscription(User $user): ?Subscription
    {
        return Subscription::where('user_id', $user->id)
            ->active()
            ->with('plan')
            ->first();
    }

    public function hasFeature(User $user, string $feature): bool
    {
        $subscription = $this->getActiveSubscription($user);

        if (!$subscription) {
            return false;
        }

        return $subscription->plan->hasFeature($feature);
    }

    public function getLimit(User $user, string $limitKey, $default = null)
    {
        $subscription = $this->getActiveSubscription($user);

        if (!$subscription) {
            return $default;
        }

        return $subscription->plan->getLimit($limitKey, $default);
    }

    public function processExpirations(): int
    {
        $expired = Subscription::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired']);

            $this->auditService->log(
                $subscription->user_id,
                'subscription_expired',
                'Subscription',
                $subscription->id,
                []
            );
        }

        return $expired->count();
    }
}

class SubscriptionResult
{
    public function __construct(
        public bool $success,
        public ?Subscription $subscription = null,
        public ?string $error = null
    ) {}
}

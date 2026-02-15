<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function send(
        User $user,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?array $data = null,
        string $severity = Notification::SEVERITY_INFO
    ): Notification {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'data' => $data,
            'severity' => $severity,
        ]);

        $this->dispatchToChannels($notification);

        return $notification;
    }

    public function sendTransactionExecuted(User $user, array $transactionData): Notification
    {
        return $this->send(
            $user,
            Notification::TYPE_TRANSACTION_EXECUTED,
            'Transaction Completed',
            "Your transaction of {$transactionData['amount']} {$transactionData['currency']} has been executed successfully.",
            '/paneta/transactions/' . $transactionData['id'],
            $transactionData,
            Notification::SEVERITY_SUCCESS
        );
    }

    public function sendCrossBorderCompleted(User $user, array $transactionData): Notification
    {
        return $this->send(
            $user,
            Notification::TYPE_CROSS_BORDER_COMPLETED,
            'Cross-Border Transfer Complete',
            "Your international transfer of {$transactionData['source_amount']} {$transactionData['source_currency']} to {$transactionData['destination_country']} has been completed.",
            '/paneta/cross-border/' . $transactionData['id'],
            $transactionData,
            Notification::SEVERITY_SUCCESS
        );
    }

    public function sendPaymentRequestPaid(User $user, array $paymentData): Notification
    {
        return $this->send(
            $user,
            Notification::TYPE_PAYMENT_REQUEST_PAID,
            'Payment Received',
            "You received a payment of {$paymentData['amount']} {$paymentData['currency']}.",
            '/paneta/payment-requests/' . $paymentData['id'],
            $paymentData,
            Notification::SEVERITY_SUCCESS
        );
    }

    public function sendSubscriptionExpiring(User $user, array $subscriptionData): Notification
    {
        return $this->send(
            $user,
            Notification::TYPE_SUBSCRIPTION_EXPIRING,
            'Subscription Expiring Soon',
            "Your {$subscriptionData['plan_name']} subscription will expire on {$subscriptionData['expires_at']}.",
            '/paneta/subscription',
            $subscriptionData,
            Notification::SEVERITY_WARNING
        );
    }

    public function sendSuspiciousActivity(User $user, array $activityData): Notification
    {
        return $this->send(
            $user,
            Notification::TYPE_SUSPICIOUS_ACTIVITY,
            'Security Alert',
            'We detected unusual activity on your account. Please review your recent transactions.',
            '/paneta/security',
            $activityData,
            Notification::SEVERITY_ERROR
        );
    }

    public function sendKycVerified(User $user): Notification
    {
        return $this->send(
            $user,
            Notification::TYPE_KYC_VERIFIED,
            'Identity Verified',
            'Your identity has been verified. You now have full access to all platform features.',
            '/paneta/dashboard',
            ['verified_at' => now()->toIso8601String()],
            Notification::SEVERITY_SUCCESS
        );
    }

    public function sendAccountLinked(User $user, array $accountData): Notification
    {
        return $this->send(
            $user,
            Notification::TYPE_ACCOUNT_LINKED,
            'Account Linked',
            "Your {$accountData['institution_name']} account has been successfully linked.",
            '/paneta/accounts',
            $accountData,
            Notification::SEVERITY_SUCCESS
        );
    }

    public function sendFxOfferMatched(User $user, array $offerData): Notification
    {
        return $this->send(
            $user,
            Notification::TYPE_FX_OFFER_MATCHED,
            'FX Offer Matched',
            "Your FX offer for {$offerData['amount']} {$offerData['sell_currency']} has been matched.",
            '/paneta/fx-offers/' . $offerData['id'],
            $offerData,
            Notification::SEVERITY_SUCCESS
        );
    }

    public function markAsRead(Notification $notification): Notification
    {
        return $notification->markAsRead();
    }

    public function markAllAsRead(User $user): int
    {
        return Notification::forUser($user)
            ->unread()
            ->update(['read_at' => now()]);
    }

    public function getUnreadCount(User $user): int
    {
        return Notification::forUser($user)->unread()->count();
    }

    public function getUserNotifications(User $user, int $limit = 50, bool $unreadOnly = false): array
    {
        $query = Notification::forUser($user)
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->get()->toArray();
    }

    public function getPreferences(User $user): NotificationPreference
    {
        return NotificationPreference::forUser($user);
    }

    public function updatePreferences(User $user, array $preferences): NotificationPreference
    {
        $pref = NotificationPreference::forUser($user);
        $pref->update($preferences);
        return $pref->fresh();
    }

    public function deleteOldNotifications(int $daysOld = 90): int
    {
        return Notification::where('created_at', '<', now()->subDays($daysOld))
            ->whereNotNull('read_at')
            ->delete();
    }

    protected function dispatchToChannels(Notification $notification): void
    {
        $user = $notification->user;
        $preferences = NotificationPreference::forUser($user);

        if ($preferences->isInQuietHours()) {
            Log::info("Notification deferred due to quiet hours", [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
            ]);
            return;
        }

        if ($preferences->shouldSendEmail($notification->type)) {
            $this->queueEmailNotification($notification);
        }

        if ($preferences->shouldSendSms($notification->type)) {
            $this->queueSmsNotification($notification);
        }

        if ($preferences->shouldSendPush($notification->type)) {
            $this->queuePushNotification($notification);
        }

        $notification->markAsSent();
    }

    protected function queueEmailNotification(Notification $notification): void
    {
        Log::info("Email notification queued", [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'type' => $notification->type,
        ]);
    }

    protected function queueSmsNotification(Notification $notification): void
    {
        Log::info("SMS notification queued", [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'type' => $notification->type,
        ]);
    }

    protected function queuePushNotification(Notification $notification): void
    {
        Log::info("Push notification queued", [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'type' => $notification->type,
        ]);
    }
}

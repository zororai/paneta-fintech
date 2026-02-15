<?php

namespace App\Services;

use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class SecurityService
{
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_MINUTES = 15;

    public function logLoginSuccess(User $user): void
    {
        SecurityLog::logEvent(
            eventType: 'login_success',
            severity: 'info',
            userId: $user->id
        );

        $this->clearLoginAttempts($user->email);
    }

    public function logLoginFailed(string $email, ?string $ipAddress = null): void
    {
        SecurityLog::logEvent(
            eventType: 'login_failed',
            severity: 'warning',
            ipAddress: $ipAddress,
            metadata: ['email' => $email]
        );

        $this->incrementLoginAttempts($email);
        $this->checkBruteForce($email, $ipAddress);
    }

    public function logSuspiciousActivity(
        ?User $user,
        string $reason,
        array $metadata = []
    ): void {
        SecurityLog::logEvent(
            eventType: 'suspicious_activity',
            severity: 'critical',
            userId: $user?->id,
            metadata: array_merge(['reason' => $reason], $metadata)
        );
    }

    public function logRateLimitExceeded(
        ?User $user,
        string $endpoint,
        ?string $ipAddress = null
    ): void {
        SecurityLog::logEvent(
            eventType: 'rate_limit_exceeded',
            severity: 'warning',
            userId: $user?->id,
            ipAddress: $ipAddress,
            metadata: ['endpoint' => $endpoint]
        );
    }

    public function logAccountLocked(User $user, string $reason): void
    {
        SecurityLog::logEvent(
            eventType: 'account_locked',
            severity: 'critical',
            userId: $user->id,
            metadata: ['reason' => $reason]
        );
    }

    public function isAccountLocked(string $email): bool
    {
        $key = "account_locked:{$email}";
        return Cache::has($key);
    }

    public function lockAccount(string $email, int $minutes = null): void
    {
        $minutes = $minutes ?? self::LOCKOUT_MINUTES;
        $key = "account_locked:{$email}";
        Cache::put($key, true, now()->addMinutes($minutes));
    }

    public function unlockAccount(string $email): void
    {
        $key = "account_locked:{$email}";
        Cache::forget($key);
        $this->clearLoginAttempts($email);
    }

    protected function incrementLoginAttempts(string $email): void
    {
        $key = "login_attempts:{$email}";
        $attempts = Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, now()->addMinutes(self::LOCKOUT_MINUTES));
    }

    protected function getLoginAttempts(string $email): int
    {
        $key = "login_attempts:{$email}";
        return Cache::get($key, 0);
    }

    protected function clearLoginAttempts(string $email): void
    {
        $key = "login_attempts:{$email}";
        Cache::forget($key);
    }

    protected function checkBruteForce(string $email, ?string $ipAddress): void
    {
        $attempts = $this->getLoginAttempts($email);

        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $this->lockAccount($email);

            SecurityLog::logEvent(
                eventType: 'account_locked',
                severity: 'critical',
                ipAddress: $ipAddress,
                metadata: [
                    'email' => $email,
                    'reason' => 'Too many failed login attempts',
                    'attempts' => $attempts,
                ]
            );
        }
    }

    public function getRecentSecurityEvents(?User $user = null, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        $query = SecurityLog::query()->recent(24);

        if ($user) {
            $query->forUser($user->id);
        }

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getCriticalEvents(int $hours = 24): \Illuminate\Database\Eloquent\Collection
    {
        return SecurityLog::critical()
            ->recent($hours)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getSecurityStats(int $hours = 24): array
    {
        $since = now()->subHours($hours);

        return [
            'total_events' => SecurityLog::where('created_at', '>=', $since)->count(),
            'failed_logins' => SecurityLog::byEventType('login_failed')
                ->where('created_at', '>=', $since)
                ->count(),
            'successful_logins' => SecurityLog::byEventType('login_success')
                ->where('created_at', '>=', $since)
                ->count(),
            'suspicious_activities' => SecurityLog::byEventType('suspicious_activity')
                ->where('created_at', '>=', $since)
                ->count(),
            'accounts_locked' => SecurityLog::byEventType('account_locked')
                ->where('created_at', '>=', $since)
                ->count(),
            'rate_limits_exceeded' => SecurityLog::byEventType('rate_limit_exceeded')
                ->where('created_at', '>=', $since)
                ->count(),
        ];
    }

    public function detectAnomalies(User $user): array
    {
        $anomalies = [];
        $recentEvents = $this->getRecentSecurityEvents($user, 100);

        $uniqueIps = $recentEvents->pluck('ip_address')->unique()->count();
        if ($uniqueIps > 5) {
            $anomalies[] = [
                'type' => 'multiple_ips',
                'description' => "Activity from {$uniqueIps} different IP addresses",
                'severity' => 'warning',
            ];
        }

        $failedLogins = $recentEvents->where('event_type', 'login_failed')->count();
        if ($failedLogins > 3) {
            $anomalies[] = [
                'type' => 'failed_logins',
                'description' => "{$failedLogins} failed login attempts",
                'severity' => 'warning',
            ];
        }

        return $anomalies;
    }
}

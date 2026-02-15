<?php

namespace App\Services;

use App\Models\AggregatedAccount;
use App\Models\InstitutionToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RefreshScheduler
{
    public function __construct(
        protected AggregationEngine $aggregationEngine,
        protected TokenVaultService $tokenVault
    ) {}

    public function getAccountsNeedingRefresh(int $staleHours = 4): Collection
    {
        return AggregatedAccount::where(function ($query) use ($staleHours) {
            $query->where('last_refreshed_at', '<', now()->subHours($staleHours))
                ->orWhereNull('last_refreshed_at');
        })
            ->where('status', '!=', 'disconnected')
            ->with(['user', 'institution'])
            ->get();
    }

    public function scheduleUserRefresh(User $user): array
    {
        $staleAccounts = $this->aggregationEngine->getStaleAccounts($user);
        
        if ($staleAccounts->isEmpty()) {
            return [
                'scheduled' => false,
                'reason' => 'No stale accounts found',
            ];
        }

        return [
            'scheduled' => true,
            'accounts_count' => $staleAccounts->count(),
        ];
    }

    public function processScheduledRefreshes(int $batchSize = 50): array
    {
        $accountsToRefresh = $this->getAccountsNeedingRefresh();
        $userIds = $accountsToRefresh->pluck('user_id')->unique()->take($batchSize);
        
        $results = [];
        
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if (!$user) {
                continue;
            }

            try {
                $results[$userId] = $this->aggregationEngine->refreshUserAccounts($user);
            } catch (\Exception $e) {
                Log::error('Scheduled refresh failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
                $results[$userId] = ['error' => $e->getMessage()];
            }
        }

        return [
            'users_processed' => count($results),
            'results' => $results,
        ];
    }

    public function refreshExpiringTokens(int $withinHours = 24): array
    {
        $expiringTokens = $this->tokenVault->getExpiringTokens($withinHours);
        $refreshed = 0;
        $failed = 0;

        foreach ($expiringTokens as $token) {
            try {
                $this->tokenVault->refreshToken($token);
                $refreshed++;
            } catch (\Exception $e) {
                Log::error('Token refresh failed', [
                    'token_id' => $token->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return [
            'tokens_checked' => $expiringTokens->count(),
            'refreshed' => $refreshed,
            'failed' => $failed,
        ];
    }

    public function markDisconnectedAccounts(): int
    {
        $expiredTokens = $this->tokenVault->getExpiredTokens();
        $disconnected = 0;

        foreach ($expiredTokens as $token) {
            AggregatedAccount::where('user_id', $token->user_id)
                ->where('institution_id', $token->institution_id)
                ->update(['status' => 'disconnected']);
            
            $disconnected++;
        }

        return $disconnected;
    }

    public function getRefreshStats(): array
    {
        return [
            'total_accounts' => AggregatedAccount::count(),
            'active_accounts' => AggregatedAccount::where('status', 'active')->count(),
            'stale_accounts' => AggregatedAccount::where('last_refreshed_at', '<', now()->subHours(4))->count(),
            'disconnected_accounts' => AggregatedAccount::where('status', 'disconnected')->count(),
            'total_tokens' => InstitutionToken::count(),
            'valid_tokens' => InstitutionToken::valid()->count(),
            'expiring_tokens_24h' => InstitutionToken::where('expires_at', '<', now()->addHours(24))
                ->where('expires_at', '>', now())
                ->count(),
        ];
    }
}

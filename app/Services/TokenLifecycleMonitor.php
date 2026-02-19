<?php

namespace App\Services;

use App\Models\InstitutionToken;
use App\Models\LinkedAccount;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * TokenLifecycleMonitor
 * 
 * Provides continuous token monitoring for consent security:
 * - Token expiry polling
 * - Revocation detection
 * - Scope change monitoring
 * - Auto-disable data access on issues
 */
class TokenLifecycleMonitor
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Monitor token expiry and return tokens needing attention
     */
    public function monitorTokenExpiry(): array
    {
        $expiringTokens = InstitutionToken::where('status', 'active')
            ->where('expires_at', '<=', now()->addDays(7))
            ->where('expires_at', '>', now())
            ->get();

        $expiredTokens = InstitutionToken::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expiredTokens as $token) {
            $this->handleExpiredToken($token);
        }

        foreach ($expiringTokens as $token) {
            $this->notifyExpiringToken($token);
        }

        return [
            'expired_count' => $expiredTokens->count(),
            'expiring_soon_count' => $expiringTokens->count(),
            'expired_tokens' => $expiredTokens->pluck('id')->toArray(),
            'expiring_tokens' => $expiringTokens->pluck('id')->toArray(),
        ];
    }

    /**
     * Detect revocation via webhook or polling
     */
    public function detectRevocationWebhook(array $webhookPayload): bool
    {
        $tokenId = $webhookPayload['token_id'] ?? null;
        $eventType = $webhookPayload['event_type'] ?? null;
        $institutionId = $webhookPayload['institution_id'] ?? null;

        if ($eventType !== 'token.revoked' && $eventType !== 'consent.revoked') {
            return false;
        }

        $token = InstitutionToken::where('external_token_id', $tokenId)
            ->orWhere('id', $tokenId)
            ->first();

        if (!$token) {
            Log::warning('Revocation webhook received for unknown token', [
                'token_id' => $tokenId,
                'institution_id' => $institutionId,
            ]);
            return false;
        }

        $this->handleRevokedToken($token, 'webhook');

        return true;
    }

    /**
     * Auto-disable data access when token is invalid
     */
    public function autoDisableDataAccess(InstitutionToken $token, string $reason): void
    {
        $token->update([
            'status' => 'disabled',
            'disabled_at' => now(),
            'disabled_reason' => $reason,
        ]);

        $linkedAccounts = LinkedAccount::where('institution_token_id', $token->id)
            ->where('status', 'active')
            ->get();

        foreach ($linkedAccounts as $account) {
            $account->update([
                'status' => 'suspended',
                'suspended_reason' => "Token disabled: {$reason}",
            ]);
        }

        $this->auditService->log(
            'data_access_disabled',
            'institution_token',
            $token->id,
            null,
            [
                'reason' => $reason,
                'affected_accounts' => $linkedAccounts->pluck('id')->toArray(),
            ]
        );

        if ($token->user_id) {
            $user = User::find($token->user_id);
            if ($user) {
                $this->notificationService->send(
                    $user,
                    'account_access_suspended',
                    'Account Access Suspended',
                    "Your linked account access has been suspended. Reason: {$reason}. Please re-link your account to restore access."
                );
            }
        }
    }

    /**
     * Monitor scope changes on tokens
     */
    public function monitorScopeChanges(InstitutionToken $token, array $newScopes): array
    {
        $originalScopes = $token->scopes ?? [];
        $addedScopes = array_diff($newScopes, $originalScopes);
        $removedScopes = array_diff($originalScopes, $newScopes);

        if (empty($addedScopes) && empty($removedScopes)) {
            return ['changed' => false];
        }

        $this->auditService->log(
            'token_scope_changed',
            'institution_token',
            $token->id,
            null,
            [
                'original_scopes' => $originalScopes,
                'new_scopes' => $newScopes,
                'added_scopes' => $addedScopes,
                'removed_scopes' => $removedScopes,
            ]
        );

        if (!empty($removedScopes)) {
            $this->handleScopeReduction($token, $removedScopes);
        }

        if (!empty($addedScopes)) {
            $this->handleScopeEscalation($token, $addedScopes);
        }

        $token->update(['scopes' => $newScopes]);

        return [
            'changed' => true,
            'added_scopes' => $addedScopes,
            'removed_scopes' => $removedScopes,
        ];
    }

    /**
     * Poll institution for token validity
     */
    public function pollTokenValidity(InstitutionToken $token): array
    {
        $result = [
            'token_id' => $token->id,
            'valid' => true,
            'checked_at' => now()->toIso8601String(),
        ];

        if ($token->expires_at && $token->expires_at->isPast()) {
            $result['valid'] = false;
            $result['reason'] = 'expired';
            $this->handleExpiredToken($token);
        }

        if ($token->status === 'revoked') {
            $result['valid'] = false;
            $result['reason'] = 'revoked';
        }

        return $result;
    }

    /**
     * Get token health report
     */
    public function getTokenHealthReport(): array
    {
        $tokens = InstitutionToken::all();

        return [
            'total_tokens' => $tokens->count(),
            'active_tokens' => $tokens->where('status', 'active')->count(),
            'expired_tokens' => $tokens->where('status', 'expired')->count(),
            'revoked_tokens' => $tokens->where('status', 'revoked')->count(),
            'disabled_tokens' => $tokens->where('status', 'disabled')->count(),
            'expiring_within_7_days' => $tokens
                ->where('status', 'active')
                ->where('expires_at', '<=', now()->addDays(7))
                ->where('expires_at', '>', now())
                ->count(),
            'tokens_by_institution' => $tokens
                ->groupBy('institution_id')
                ->map(fn ($group) => [
                    'total' => $group->count(),
                    'active' => $group->where('status', 'active')->count(),
                ])
                ->toArray(),
        ];
    }

    /**
     * Handle expired token
     */
    private function handleExpiredToken(InstitutionToken $token): void
    {
        $token->update([
            'status' => 'expired',
            'expired_at' => now(),
        ]);

        $this->autoDisableDataAccess($token, 'Token expired');

        $this->auditService->log(
            'token_expired',
            'institution_token',
            $token->id,
            null,
            ['expired_at' => $token->expires_at->toIso8601String()]
        );
    }

    /**
     * Handle revoked token
     */
    private function handleRevokedToken(InstitutionToken $token, string $source): void
    {
        $token->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revocation_source' => $source,
        ]);

        $this->autoDisableDataAccess($token, 'Token revoked');

        $this->auditService->log(
            'token_revoked',
            'institution_token',
            $token->id,
            null,
            [
                'revocation_source' => $source,
                'revoked_at' => now()->toIso8601String(),
            ]
        );
    }

    /**
     * Notify about expiring token
     */
    private function notifyExpiringToken(InstitutionToken $token): void
    {
        if ($token->user_id) {
            $user = User::find($token->user_id);
            if ($user) {
                $this->notificationService->send(
                    $user,
                    'token_expiring_soon',
                    'Account Access Expiring Soon',
                    "Your linked account access expires in " . now()->diffInDays($token->expires_at) . " days. Please refresh your consent to maintain access."
                );
            }
        }
    }

    /**
     * Handle scope reduction
     */
    private function handleScopeReduction(InstitutionToken $token, array $removedScopes): void
    {
        Log::info('Token scope reduced', [
            'token_id' => $token->id,
            'removed_scopes' => $removedScopes,
        ]);
    }

    /**
     * Handle scope escalation (security concern)
     */
    private function handleScopeEscalation(InstitutionToken $token, array $addedScopes): void
    {
        Log::warning('Token scope escalation detected', [
            'token_id' => $token->id,
            'added_scopes' => $addedScopes,
        ]);

        $this->auditService->log(
            'token_scope_escalation_detected',
            'institution_token',
            $token->id,
            null,
            [
                'added_scopes' => $addedScopes,
                'security_alert' => true,
            ]
        );
    }
}

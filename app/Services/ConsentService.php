<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\LinkedAccount;
use App\Models\User;
use Illuminate\Support\Str;

class ConsentService
{
    private const CONSENT_VALIDITY_DAYS = 30;

    public function initiateConsent(User $user, Institution $institution): array
    {
        // Simulate OAuth-style redirect flow
        $state = Str::random(32);
        
        return [
            'redirect_url' => $this->buildMockRedirectUrl($institution, $state),
            'state' => $state,
            'institution' => [
                'id' => $institution->id,
                'name' => $institution->name,
                'type' => $institution->type,
            ],
        ];
    }

    public function completeConsent(
        User $user,
        Institution $institution,
        string $currency = 'USD',
        ?string $accountNumber = null,
        ?string $accountHolderName = null,
        ?string $country = null
    ): LinkedAccount {
        // Generate mock consent token
        $consentToken = $this->generateConsentToken();
        
        // Use provided account number or generate mock account identifier
        $accountIdentifier = $accountNumber ?? $this->generateAccountIdentifier($institution);
        
        // Generate random mock balance
        $mockBalance = $this->generateMockBalance();

        return LinkedAccount::create([
            'user_id' => $user->id,
            'institution_id' => $institution->id,
            'country' => $country,
            'account_identifier' => $accountIdentifier,
            'account_holder_name' => $accountHolderName,
            'currency' => $currency,
            'mock_balance' => $mockBalance,
            'consent_token' => $consentToken,
            'consent_expires_at' => now()->addDays(self::CONSENT_VALIDITY_DAYS),
            'status' => 'active',
        ]);
    }

    public function revokeConsent(LinkedAccount $account): void
    {
        $account->update([
            'status' => 'revoked',
            'consent_token' => null,
        ]);
    }

    public function refreshConsent(LinkedAccount $account): LinkedAccount
    {
        $newToken = $this->generateConsentToken();
        
        $account->update([
            'consent_token' => $newToken,
            'consent_expires_at' => now()->addDays(self::CONSENT_VALIDITY_DAYS),
            'status' => 'active',
        ]);

        return $account->fresh();
    }

    public function isConsentExpired(LinkedAccount $account): bool
    {
        return $account->consent_expires_at->isPast();
    }

    public function checkAndUpdateExpiredConsents(User $user): int
    {
        $expiredCount = 0;
        
        $accounts = $user->linkedAccounts()
            ->where('status', 'active')
            ->where('consent_expires_at', '<', now())
            ->get();

        foreach ($accounts as $account) {
            $account->update(['status' => 'expired']);
            $expiredCount++;
        }

        return $expiredCount;
    }

    private function buildMockRedirectUrl(Institution $institution, string $state): string
    {
        $baseUrl = $institution->api_endpoint ?? "https://mock.{$institution->name}.com/oauth";
        return "{$baseUrl}/authorize?state={$state}&client_id=paneta";
    }

    private function generateConsentToken(): string
    {
        return Str::random(64);
    }

    private function generateAccountIdentifier(Institution $institution): string
    {
        $prefix = match ($institution->type) {
            'bank' => 'ACC',
            'wallet' => 'WAL',
            'fx_provider' => 'FXA',
            default => 'ACC',
        };

        return $prefix . '-' . strtoupper(Str::random(8)) . '-' . random_int(1000, 9999);
    }

    private function generateMockBalance(): float
    {
        // Generate a realistic mock balance between 100 and 50000
        return round(random_int(10000, 5000000) / 100, 2);
    }
}

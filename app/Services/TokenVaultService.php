<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\InstitutionToken;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class TokenVaultService
{
    public function storeToken(
        User $user,
        Institution $institution,
        string $accessToken,
        ?string $refreshToken = null,
        ?int $expiresInSeconds = null,
        array $scopes = []
    ): InstitutionToken {
        $expiresAt = $expiresInSeconds 
            ? now()->addSeconds($expiresInSeconds) 
            : now()->addDays(30);

        return InstitutionToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'institution_id' => $institution->id,
            ],
            [
                'encrypted_token' => Crypt::encryptString($accessToken),
                'encrypted_refresh_token' => $refreshToken 
                    ? Crypt::encryptString($refreshToken) 
                    : null,
                'expires_at' => $expiresAt,
                'scopes' => $scopes,
            ]
        );
    }

    public function getToken(User $user, Institution $institution): ?InstitutionToken
    {
        return InstitutionToken::where('user_id', $user->id)
            ->where('institution_id', $institution->id)
            ->first();
    }

    public function getDecryptedToken(InstitutionToken $token): string
    {
        return Crypt::decryptString($token->encrypted_token);
    }

    public function getDecryptedRefreshToken(InstitutionToken $token): ?string
    {
        if (!$token->encrypted_refresh_token) {
            return null;
        }
        return Crypt::decryptString($token->encrypted_refresh_token);
    }

    public function refreshToken(InstitutionToken $token): InstitutionToken
    {
        $newToken = $this->generateMockToken();
        $newRefreshToken = $this->generateMockToken();

        $token->update([
            'encrypted_token' => Crypt::encryptString($newToken),
            'encrypted_refresh_token' => Crypt::encryptString($newRefreshToken),
            'expires_at' => now()->addDays(30),
        ]);

        return $token->fresh();
    }

    public function revokeToken(InstitutionToken $token): bool
    {
        return $token->delete();
    }

    public function revokeAllTokens(User $user): int
    {
        return InstitutionToken::where('user_id', $user->id)->delete();
    }

    public function isTokenValid(InstitutionToken $token): bool
    {
        return $token->isValid();
    }

    public function getExpiredTokens(): \Illuminate\Database\Eloquent\Collection
    {
        return InstitutionToken::where('expires_at', '<', now())->get();
    }

    public function getExpiringTokens(int $withinHours = 24): \Illuminate\Database\Eloquent\Collection
    {
        return InstitutionToken::where('expires_at', '>', now())
            ->where('expires_at', '<', now()->addHours($withinHours))
            ->get();
    }

    public function hasScope(InstitutionToken $token, string $scope): bool
    {
        $scopes = $token->scopes ?? [];
        return in_array($scope, $scopes);
    }

    protected function generateMockToken(): string
    {
        return 'tk_' . Str::random(40);
    }

    public function generateMockAuthCode(): string
    {
        return 'auth_' . Str::random(32);
    }
}

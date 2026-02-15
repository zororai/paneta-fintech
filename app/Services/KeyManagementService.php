<?php

namespace App\Services;

use App\Models\KeyRotation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KeyManagementService
{
    const ROTATION_INTERVAL_DAYS = 90;
    const DEPRECATION_GRACE_DAYS = 30;
    const CACHE_TTL_SECONDS = 300;

    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function getCurrentKey(string $keyType): ?KeyRotation
    {
        $cacheKey = "key_rotation:{$keyType}:current";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($keyType) {
            return KeyRotation::getCurrentVersion($keyType);
        });
    }

    public function getKeyByVersion(string $keyType, int $version): ?KeyRotation
    {
        return KeyRotation::forType($keyType)
            ->where('version', $version)
            ->first();
    }

    public function getValidKeysForVerification(string $keyType): array
    {
        $cacheKey = "key_rotation:{$keyType}:valid_keys";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($keyType) {
            return KeyRotation::forType($keyType)
                ->validForVerification()
                ->orderByDesc('version')
                ->get()
                ->toArray();
        });
    }

    public function rotateKey(string $keyType, User $rotator, string $reason = 'Scheduled rotation'): KeyRotation
    {
        return DB::transaction(function () use ($keyType, $rotator, $reason) {
            $currentKey = $this->getCurrentKey($keyType);

            if ($currentKey) {
                $currentKey->deprecate();
            }

            $newVersion = KeyRotation::getNextVersion($keyType);
            $newSecret = $this->generateSecret();

            $newKey = KeyRotation::create([
                'key_type' => $keyType,
                'version' => $newVersion,
                'key_hash' => hash('sha256', $newSecret),
                'status' => KeyRotation::STATUS_ACTIVE,
                'activated_at' => now(),
                'expires_at' => now()->addDays(self::ROTATION_INTERVAL_DAYS + self::DEPRECATION_GRACE_DAYS),
                'rotated_by' => $rotator->id,
                'rotation_reason' => $reason,
                'metadata' => [
                    'previous_version' => $currentKey?->version,
                    'rotation_timestamp' => now()->toIso8601String(),
                ],
            ]);

            $this->clearKeyCache($keyType);

            $this->auditService->log(
                $rotator->id,
                'key_rotated',
                'KeyRotation',
                $newKey->id,
                [
                    'key_type' => $keyType,
                    'new_version' => $newVersion,
                    'previous_version' => $currentKey?->version,
                    'reason' => $reason,
                ]
            );

            Log::info("Key rotated", [
                'key_type' => $keyType,
                'new_version' => $newVersion,
                'rotated_by' => $rotator->id,
            ]);

            return $newKey;
        });
    }

    public function revokeKey(string $keyType, int $version, User $revoker, string $reason = 'Security compromise'): bool
    {
        return DB::transaction(function () use ($keyType, $version, $revoker, $reason) {
            $key = $this->getKeyByVersion($keyType, $version);

            if (!$key) {
                return false;
            }

            $key->revoke();

            $this->clearKeyCache($keyType);

            $this->auditService->log(
                $revoker->id,
                'key_revoked',
                'KeyRotation',
                $key->id,
                [
                    'key_type' => $keyType,
                    'version' => $version,
                    'reason' => $reason,
                ]
            );

            Log::warning("Key revoked", [
                'key_type' => $keyType,
                'version' => $version,
                'revoked_by' => $revoker->id,
                'reason' => $reason,
            ]);

            return true;
        });
    }

    public function handleKeyCompromise(string $keyType, User $handler): array
    {
        return DB::transaction(function () use ($keyType, $handler) {
            $revokedKeys = [];

            $activeKeys = KeyRotation::forType($keyType)
                ->whereIn('status', [KeyRotation::STATUS_ACTIVE, KeyRotation::STATUS_DEPRECATED])
                ->get();

            foreach ($activeKeys as $key) {
                $key->revoke();
                $revokedKeys[] = $key->version;
            }

            $newKey = $this->rotateKey($keyType, $handler, 'Emergency rotation due to compromise');

            $this->clearKeyCache($keyType);

            $this->auditService->log(
                $handler->id,
                'key_compromise_handled',
                'KeyRotation',
                $newKey->id,
                [
                    'key_type' => $keyType,
                    'revoked_versions' => $revokedKeys,
                    'new_version' => $newKey->version,
                ]
            );

            Log::critical("Key compromise handled", [
                'key_type' => $keyType,
                'revoked_versions' => $revokedKeys,
                'new_version' => $newKey->version,
                'handled_by' => $handler->id,
            ]);

            return [
                'revoked_versions' => $revokedKeys,
                'new_key' => $newKey,
            ];
        });
    }

    public function signPayload(array $payload, string $keyType = KeyRotation::KEY_TYPE_INSTRUCTION): array
    {
        $currentKey = $this->getCurrentKey($keyType);

        if (!$currentKey || !$currentKey->isValidForSigning()) {
            throw new \RuntimeException("No valid signing key available for type: {$keyType}");
        }

        $secret = $this->getSecretForKey($currentKey);
        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        return [
            'signature' => $signature,
            'key_version' => $currentKey->version,
        ];
    }

    public function verifySignature(string $signature, array $payload, int $keyVersion, string $keyType = KeyRotation::KEY_TYPE_INSTRUCTION): bool
    {
        $key = $this->getKeyByVersion($keyType, $keyVersion);

        if (!$key || !$key->isValidForVerification()) {
            return false;
        }

        $secret = $this->getSecretForKey($key);
        $expectedSignature = hash_hmac('sha256', json_encode($payload), $secret);

        return hash_equals($expectedSignature, $signature);
    }

    public function getKeysNeedingRotation(): array
    {
        $keyTypes = [
            KeyRotation::KEY_TYPE_INSTRUCTION,
            KeyRotation::KEY_TYPE_TOKEN_ENCRYPTION,
            KeyRotation::KEY_TYPE_HMAC,
        ];

        $needsRotation = [];

        foreach ($keyTypes as $keyType) {
            $currentKey = $this->getCurrentKey($keyType);

            if (!$currentKey) {
                $needsRotation[] = [
                    'key_type' => $keyType,
                    'reason' => 'No active key exists',
                ];
                continue;
            }

            $daysUntilExpiry = now()->diffInDays($currentKey->expires_at, false);

            if ($daysUntilExpiry <= self::DEPRECATION_GRACE_DAYS) {
                $needsRotation[] = [
                    'key_type' => $keyType,
                    'reason' => "Key expires in {$daysUntilExpiry} days",
                    'current_version' => $currentKey->version,
                    'expires_at' => $currentKey->expires_at->toIso8601String(),
                ];
            }
        }

        return $needsRotation;
    }

    public function getKeyStats(): array
    {
        return [
            'total_keys' => KeyRotation::count(),
            'active_keys' => KeyRotation::active()->count(),
            'deprecated_keys' => KeyRotation::where('status', KeyRotation::STATUS_DEPRECATED)->count(),
            'revoked_keys' => KeyRotation::where('status', KeyRotation::STATUS_REVOKED)->count(),
            'keys_by_type' => KeyRotation::selectRaw('key_type, status, COUNT(*) as count')
                ->groupBy('key_type', 'status')
                ->get()
                ->groupBy('key_type')
                ->toArray(),
            'needs_rotation' => $this->getKeysNeedingRotation(),
        ];
    }

    protected function generateSecret(): string
    {
        return Str::random(64);
    }

    protected function getSecretForKey(KeyRotation $key): string
    {
        return config("paneta.keys.{$key->key_type}.v{$key->version}", config('paneta.instruction_secret'));
    }

    protected function clearKeyCache(string $keyType): void
    {
        Cache::forget("key_rotation:{$keyType}:current");
        Cache::forget("key_rotation:{$keyType}:valid_keys");
    }
}

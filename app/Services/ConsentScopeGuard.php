<?php

namespace App\Services;

use App\Models\InstitutionToken;
use App\Models\LinkedAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * ConsentScopeGuard
 * 
 * Enforces consent scope boundaries:
 * - Validation of read-only token scope
 * - Prevention of write-scope escalation
 * - Scope change detection
 */
class ConsentScopeGuard
{
    private const READ_ONLY_SCOPES = [
        'accounts:read',
        'balances:read',
        'transactions:read',
        'identity:read',
        'statements:read',
    ];

    private const WRITE_SCOPES = [
        'accounts:write',
        'payments:write',
        'transfers:write',
        'beneficiaries:write',
    ];

    private const PRIVILEGED_SCOPES = [
        'admin:read',
        'admin:write',
        'system:access',
    ];

    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Validate token has read-only scope
     */
    public function validateReadOnlyScope(InstitutionToken $token): array
    {
        $tokenScopes = $token->scopes ?? [];
        $hasWriteScope = false;
        $writeScopes = [];

        foreach ($tokenScopes as $scope) {
            if (in_array($scope, self::WRITE_SCOPES)) {
                $hasWriteScope = true;
                $writeScopes[] = $scope;
            }
        }

        $result = [
            'token_id' => $token->id,
            'is_read_only' => !$hasWriteScope,
            'scopes' => $tokenScopes,
            'write_scopes_present' => $writeScopes,
            'validated_at' => now()->toIso8601String(),
        ];

        if ($hasWriteScope) {
            $this->auditService->log(
                'write_scope_detected',
                'institution_token',
                $token->id,
                null,
                [
                    'write_scopes' => $writeScopes,
                    'warning' => 'Token has write permissions beyond read-only requirement',
                ]
            );

            Log::warning('Token with write scope detected', [
                'token_id' => $token->id,
                'write_scopes' => $writeScopes,
            ]);
        }

        return $result;
    }

    /**
     * Block privilege escalation attempts
     */
    public function blockPrivilegeEscalation(
        InstitutionToken $token,
        array $requestedScopes
    ): array {
        $currentScopes = $token->scopes ?? [];
        $escalationAttempts = [];

        foreach ($requestedScopes as $scope) {
            if (in_array($scope, self::PRIVILEGED_SCOPES)) {
                $escalationAttempts[] = [
                    'scope' => $scope,
                    'type' => 'privileged_scope',
                ];
            }

            if (in_array($scope, self::WRITE_SCOPES) && !in_array($scope, $currentScopes)) {
                $isReadEquivalent = str_replace(':write', ':read', $scope);
                if (in_array($isReadEquivalent, $currentScopes)) {
                    $escalationAttempts[] = [
                        'scope' => $scope,
                        'type' => 'read_to_write_escalation',
                        'current_scope' => $isReadEquivalent,
                    ];
                }
            }
        }

        if (!empty($escalationAttempts)) {
            $this->auditService->log(
                'privilege_escalation_blocked',
                'institution_token',
                $token->id,
                null,
                [
                    'escalation_attempts' => $escalationAttempts,
                    'blocked_at' => now()->toIso8601String(),
                ]
            );

            throw new \RuntimeException(
                'Privilege escalation attempt blocked. Cannot upgrade from read-only to write permissions.'
            );
        }

        return [
            'escalation_blocked' => false,
            'requested_scopes' => $requestedScopes,
            'current_scopes' => $currentScopes,
        ];
    }

    /**
     * Validate scope is sufficient for operation
     */
    public function validateScopeForOperation(
        InstitutionToken $token,
        string $operation
    ): bool {
        $requiredScopes = $this->getRequiredScopesForOperation($operation);
        $tokenScopes = $token->scopes ?? [];

        foreach ($requiredScopes as $required) {
            if (!in_array($required, $tokenScopes)) {
                $this->auditService->log(
                    'insufficient_scope',
                    'institution_token',
                    $token->id,
                    null,
                    [
                        'operation' => $operation,
                        'required_scope' => $required,
                        'available_scopes' => $tokenScopes,
                    ]
                );

                return false;
            }
        }

        return true;
    }

    /**
     * Get minimal required scopes for account linking
     */
    public function getMinimalRequiredScopes(): array
    {
        return [
            'accounts:read',
            'balances:read',
        ];
    }

    /**
     * Validate linked account consent scope
     */
    public function validateLinkedAccountScope(LinkedAccount $account): array
    {
        $token = $account->institutionToken;

        if (!$token) {
            return [
                'valid' => false,
                'reason' => 'No associated token found',
            ];
        }

        $readOnlyValidation = $this->validateReadOnlyScope($token);
        $minimalScopes = $this->getMinimalRequiredScopes();
        $tokenScopes = $token->scopes ?? [];

        $hasMinimalScopes = empty(array_diff($minimalScopes, $tokenScopes));

        return [
            'valid' => $hasMinimalScopes,
            'is_read_only' => $readOnlyValidation['is_read_only'],
            'has_minimal_scopes' => $hasMinimalScopes,
            'missing_scopes' => array_diff($minimalScopes, $tokenScopes),
            'account_id' => $account->id,
        ];
    }

    /**
     * Enforce read-only access for data aggregation
     */
    public function enforceReadOnlyForAggregation(InstitutionToken $token): void
    {
        $validation = $this->validateReadOnlyScope($token);

        if (!$validation['is_read_only']) {
            $this->auditService->log(
                'aggregation_write_scope_rejected',
                'institution_token',
                $token->id,
                null,
                [
                    'reason' => 'Data aggregation requires read-only scope',
                    'write_scopes_present' => $validation['write_scopes_present'],
                ]
            );

            throw new \RuntimeException(
                'Data aggregation requires read-only consent. Write permissions are not permitted for aggregation services.'
            );
        }
    }

    /**
     * Get required scopes for operation
     */
    private function getRequiredScopesForOperation(string $operation): array
    {
        $operationScopes = [
            'read_accounts' => ['accounts:read'],
            'read_balances' => ['balances:read'],
            'read_transactions' => ['transactions:read'],
            'read_identity' => ['identity:read'],
            'read_statements' => ['statements:read'],
            'initiate_payment' => ['payments:write'],
            'create_transfer' => ['transfers:write'],
        ];

        return $operationScopes[$operation] ?? [];
    }
}

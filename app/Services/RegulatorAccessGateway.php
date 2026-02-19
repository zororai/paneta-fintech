<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\TransactionIntent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * RegulatorAccessGateway
 * 
 * Provides dedicated regulator access layer:
 * - Read-only audit access
 * - Scoped visibility
 * - Mutation operation restrictions
 * - Access logging
 */
class RegulatorAccessGateway
{
    private const ALLOWED_ENTITY_TYPES = [
        'audit_log',
        'transaction_intent',
        'user',
        'linked_account',
        'fx_quote',
        'compliance_case',
    ];

    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Provide read-only audit access
     */
    public function provideReadOnlyAuditAccess(
        User $regulator,
        array $filters = [],
        int $limit = 100
    ): array {
        $this->validateRegulatorAccess($regulator);
        $this->logRegulatorAccess($regulator, 'audit_logs', $filters);

        $query = AuditLog::with('user');

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['entity_type'])) {
            $this->validateEntityTypeAccess($filters['entity_type']);
            $query->where('entity_type', $filters['entity_type']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return [
            'data' => $this->sanitizeForRegulator($logs),
            'count' => $logs->count(),
            'filters_applied' => $filters,
            'accessed_at' => now()->toIso8601String(),
            'accessed_by' => $regulator->id,
        ];
    }

    /**
     * Restrict mutation operations
     */
    public function restrictMutationOperations(User $regulator, string $operation): void
    {
        $this->validateRegulatorAccess($regulator);

        $mutationOperations = [
            'create', 'update', 'delete', 'modify', 'execute',
            'approve', 'reject', 'cancel', 'process',
        ];

        if (in_array(strtolower($operation), $mutationOperations)) {
            $this->auditService->log(
                'regulator_mutation_blocked',
                'regulator_access',
                null,
                $regulator,
                [
                    'attempted_operation' => $operation,
                    'blocked_at' => now()->toIso8601String(),
                ]
            );

            throw new \RuntimeException(
                'Regulator access is read-only. Mutation operations are not permitted.'
            );
        }
    }

    /**
     * Log regulator access
     */
    public function logRegulatorAccess(
        User $regulator,
        string $resourceType,
        array $accessDetails = []
    ): void {
        $this->auditService->log(
            'regulator_access',
            $resourceType,
            null,
            $regulator,
            [
                'access_type' => 'read',
                'resource_type' => $resourceType,
                'filters' => $accessDetails,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'accessed_at' => now()->toIso8601String(),
            ]
        );

        Log::info('Regulator access logged', [
            'regulator_id' => $regulator->id,
            'resource_type' => $resourceType,
        ]);
    }

    /**
     * Get transaction reports for regulator
     */
    public function getTransactionReport(
        User $regulator,
        array $filters = []
    ): array {
        $this->validateRegulatorAccess($regulator);
        $this->logRegulatorAccess($regulator, 'transaction_report', $filters);

        $query = TransactionIntent::with(['user', 'issuerAccount.institution']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['min_amount'])) {
            $query->where('amount', '>=', $filters['min_amount']);
        }

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        return [
            'summary' => [
                'total_count' => $transactions->count(),
                'total_volume' => $transactions->sum('amount'),
                'by_status' => $transactions->groupBy('status')
                    ->map(fn ($group) => [
                        'count' => $group->count(),
                        'volume' => $group->sum('amount'),
                    ])->toArray(),
                'by_currency' => $transactions->groupBy('currency')
                    ->map(fn ($group) => [
                        'count' => $group->count(),
                        'volume' => $group->sum('amount'),
                    ])->toArray(),
            ],
            'transactions' => $this->sanitizeTransactionsForRegulator($transactions),
            'report_generated_at' => now()->toIso8601String(),
            'generated_by' => $regulator->id,
        ];
    }

    /**
     * Get compliance summary
     */
    public function getComplianceSummary(User $regulator): array
    {
        $this->validateRegulatorAccess($regulator);
        $this->logRegulatorAccess($regulator, 'compliance_summary');

        return [
            'user_statistics' => [
                'total_users' => User::count(),
                'verified_users' => User::where('kyc_status', 'verified')->count(),
                'pending_verification' => User::where('kyc_status', 'pending')->count(),
                'rejected_users' => User::where('kyc_status', 'rejected')->count(),
            ],
            'transaction_statistics' => [
                'total_transactions' => TransactionIntent::count(),
                'executed' => TransactionIntent::where('status', 'executed')->count(),
                'failed' => TransactionIntent::where('status', 'failed')->count(),
                'pending' => TransactionIntent::where('status', 'pending')->count(),
            ],
            'audit_statistics' => [
                'total_audit_entries' => AuditLog::count(),
                'last_24_hours' => AuditLog::where('created_at', '>=', now()->subDay())->count(),
                'last_7_days' => AuditLog::where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'report_generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Export audit data for regulatory submission
     */
    public function exportAuditData(
        User $regulator,
        string $startDate,
        string $endDate,
        string $format = 'json'
    ): array {
        $this->validateRegulatorAccess($regulator);
        $this->logRegulatorAccess($regulator, 'audit_export', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'format' => $format,
        ]);

        $logs = AuditLog::whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->get();

        return [
            'export_id' => uniqid('REG_EXPORT_'),
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'record_count' => $logs->count(),
            'data' => $this->sanitizeForRegulator($logs),
            'exported_at' => now()->toIso8601String(),
            'exported_by' => $regulator->id,
            'format' => $format,
        ];
    }

    /**
     * Validate user has regulator access
     */
    private function validateRegulatorAccess(User $user): void
    {
        if ($user->role !== 'regulator' && $user->role !== 'admin') {
            $this->auditService->log(
                'unauthorized_regulator_access_attempt',
                'regulator_access',
                null,
                $user,
                [
                    'user_role' => $user->role,
                ]
            );

            throw new \RuntimeException('Access denied. Regulator credentials required.');
        }
    }

    /**
     * Validate entity type access
     */
    private function validateEntityTypeAccess(string $entityType): void
    {
        if (!in_array($entityType, self::ALLOWED_ENTITY_TYPES)) {
            throw new \RuntimeException("Access to entity type '{$entityType}' is not permitted.");
        }
    }

    /**
     * Sanitize data for regulator view
     */
    private function sanitizeForRegulator(Collection $logs): array
    {
        return $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'user_id' => $log->user_id,
                'created_at' => $log->created_at->toIso8601String(),
                'metadata' => $this->redactSensitiveData($log->metadata ?? []),
            ];
        })->toArray();
    }

    /**
     * Sanitize transactions for regulator
     */
    private function sanitizeTransactionsForRegulator(Collection $transactions): array
    {
        return $transactions->map(function ($tx) {
            return [
                'id' => $tx->id,
                'user_id' => $tx->user_id,
                'amount' => $tx->amount,
                'currency' => $tx->currency,
                'status' => $tx->status,
                'created_at' => $tx->created_at->toIso8601String(),
                'institution' => $tx->issuerAccount?->institution?->name,
            ];
        })->toArray();
    }

    /**
     * Redact sensitive data from metadata
     */
    private function redactSensitiveData(array $metadata): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'credential'];

        foreach ($metadata as $key => $value) {
            foreach ($sensitiveKeys as $sensitive) {
                if (stripos($key, $sensitive) !== false) {
                    $metadata[$key] = '[REDACTED]';
                    break;
                }
            }

            if (is_array($value)) {
                $metadata[$key] = $this->redactSensitiveData($value);
            }
        }

        return $metadata;
    }
}

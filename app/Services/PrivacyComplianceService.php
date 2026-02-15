<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DataExportRequest;
use App\Models\LinkedAccount;
use App\Models\SecurityLog;
use App\Models\TransactionIntent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrivacyComplianceService
{
    const DATA_TYPES = [
        'personal_info',
        'linked_accounts',
        'transactions',
        'audit_logs',
        'security_logs',
        'notifications',
        'preferences',
    ];

    const RETENTION_PERIODS = [
        'audit_logs' => 2555,
        'security_logs' => 1095,
        'transactions' => -1,
        'login_attempts' => 365,
    ];

    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function createDataExportRequest(
        User $user,
        string $requestType = DataExportRequest::TYPE_DATA_EXPORT,
        ?array $dataTypes = null
    ): DataExportRequest {
        $request = DataExportRequest::create([
            'user_id' => $user->id,
            'request_type' => $requestType,
            'requested_data_types' => $dataTypes ?? self::DATA_TYPES,
            'metadata' => [
                'requested_at' => now()->toIso8601String(),
                'ip_address' => request()->ip(),
            ],
        ]);

        $this->auditService->log(
            $user->id,
            'data_export_requested',
            'DataExportRequest',
            $request->id,
            ['request_type' => $requestType]
        );

        return $request;
    }

    public function processDataExportRequest(DataExportRequest $request, User $processor): DataExportRequest
    {
        $request->markAsProcessing();

        try {
            $data = $this->collectUserData($request->user, $request->requested_data_types ?? self::DATA_TYPES);

            $filename = "data-export-{$request->user_id}-" . Str::random(16) . ".json";
            $path = "exports/{$filename}";

            Storage::put($path, json_encode($data, JSON_PRETTY_PRINT));

            $downloadUrl = Storage::temporaryUrl($path, now()->addDays(7));

            $request->markAsCompleted($downloadUrl, $processor);

            $this->auditService->log(
                $processor->id,
                'data_export_completed',
                'DataExportRequest',
                $request->id,
                ['user_id' => $request->user_id]
            );

            return $request;
        } catch (\Exception $e) {
            Log::error("Data export failed", [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            $request->markAsRejected("Export failed: " . $e->getMessage(), $processor);
            throw $e;
        }
    }

    public function collectUserData(User $user, array $dataTypes): array
    {
        $data = [
            'export_date' => now()->toIso8601String(),
            'user_id' => $user->id,
        ];

        foreach ($dataTypes as $type) {
            $data[$type] = match ($type) {
                'personal_info' => $this->getPersonalInfo($user),
                'linked_accounts' => $this->getLinkedAccounts($user),
                'transactions' => $this->getTransactions($user),
                'audit_logs' => $this->getAuditLogs($user),
                'security_logs' => $this->getSecurityLogs($user),
                'notifications' => $this->getNotifications($user),
                'preferences' => $this->getPreferences($user),
                default => null,
            };
        }

        return $data;
    }

    protected function getPersonalInfo(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'kyc_status' => $user->kyc_status,
            'risk_tier' => $user->risk_tier,
            'role' => $user->role,
            'created_at' => $user->created_at?->toIso8601String(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ];
    }

    protected function getLinkedAccounts(User $user): array
    {
        return $user->linkedAccounts()
            ->with('institution:id,name')
            ->get()
            ->map(fn($account) => [
                'id' => $account->id,
                'institution' => $account->institution?->name,
                'account_identifier' => $this->maskAccountIdentifier($account->account_identifier),
                'currency' => $account->currency,
                'status' => $account->status,
                'created_at' => $account->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    protected function getTransactions(User $user): array
    {
        return $user->transactionIntents()
            ->get()
            ->map(fn($tx) => [
                'id' => $tx->id,
                'reference' => $tx->reference,
                'amount' => $tx->amount,
                'currency' => $tx->currency,
                'status' => $tx->status,
                'created_at' => $tx->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    protected function getAuditLogs(User $user): array
    {
        return AuditLog::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get()
            ->map(fn($log) => [
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    protected function getSecurityLogs(User $user): array
    {
        return SecurityLog::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn($log) => [
                'event_type' => $log->event_type,
                'severity' => $log->severity,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    protected function getNotifications(User $user): array
    {
        return $user->notifications()
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn($n) => [
                'type' => $n->type,
                'title' => $n->title,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    protected function getPreferences(User $user): array
    {
        $prefs = $user->notificationPreference;
        if (!$prefs) {
            return [];
        }

        return [
            'email_enabled' => $prefs->email_enabled,
            'sms_enabled' => $prefs->sms_enabled,
            'push_enabled' => $prefs->push_enabled,
            'preferred_language' => $prefs->preferred_language,
            'timezone' => $prefs->timezone,
        ];
    }

    public function processDataDeletionRequest(DataExportRequest $request, User $processor): DataExportRequest
    {
        return DB::transaction(function () use ($request, $processor) {
            $user = $request->user;

            $user->update([
                'name' => 'Deleted User',
                'email' => "deleted-{$user->id}@deleted.local",
                'password' => bcrypt(Str::random(64)),
            ]);

            LinkedAccount::where('user_id', $user->id)->delete();

            $this->auditService->log(
                $processor->id,
                'data_deletion_completed',
                'DataExportRequest',
                $request->id,
                ['user_id' => $user->id]
            );

            $request->markAsCompleted('', $processor);

            return $request;
        });
    }

    public function maskPii(string $value, string $type = 'default'): string
    {
        return match ($type) {
            'email' => $this->maskEmail($value),
            'phone' => $this->maskPhone($value),
            'account' => $this->maskAccountIdentifier($value),
            default => $this->maskGeneric($value),
        };
    }

    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***.***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        $maskedLocal = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2));
        return $maskedLocal . '@' . $domain;
    }

    protected function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 4) {
            return '****';
        }

        return str_repeat('*', strlen($digits) - 4) . substr($digits, -4);
    }

    protected function maskAccountIdentifier(string $identifier): string
    {
        if (strlen($identifier) <= 4) {
            return str_repeat('*', strlen($identifier));
        }

        return str_repeat('*', strlen($identifier) - 4) . substr($identifier, -4);
    }

    protected function maskGeneric(string $value): string
    {
        $length = strlen($value);
        if ($length <= 2) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 1) . str_repeat('*', $length - 2) . substr($value, -1);
    }

    public function getRetentionPolicy(): array
    {
        return self::RETENTION_PERIODS;
    }

    public function cleanupExpiredData(): array
    {
        $cleaned = [];

        foreach (self::RETENTION_PERIODS as $dataType => $days) {
            if ($days < 0) {
                continue;
            }

            $cutoffDate = now()->subDays($days);

            $count = match ($dataType) {
                'security_logs' => SecurityLog::where('created_at', '<', $cutoffDate)->delete(),
                'login_attempts' => SecurityLog::where('event_type', 'login_failed')
                    ->where('created_at', '<', $cutoffDate)
                    ->delete(),
                default => 0,
            };

            $cleaned[$dataType] = $count;
        }

        Log::info("Privacy cleanup completed", $cleaned);

        return $cleaned;
    }

    public function generateComplianceReport(): array
    {
        return [
            'report_date' => now()->toIso8601String(),
            'total_users' => User::count(),
            'data_export_requests' => [
                'pending' => DataExportRequest::pending()->count(),
                'completed_last_30_days' => DataExportRequest::where('status', 'completed')
                    ->where('processed_at', '>=', now()->subDays(30))
                    ->count(),
            ],
            'retention_policy' => self::RETENTION_PERIODS,
            'pii_fields_encrypted' => [
                'consent_tokens',
                'institution_tokens',
            ],
            'pii_fields_masked_in_logs' => [
                'account_identifiers',
                'email_addresses',
            ],
        ];
    }
}

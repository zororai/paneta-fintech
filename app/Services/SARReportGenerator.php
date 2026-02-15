<?php

namespace App\Services;

use App\Models\User;
use App\Models\ComplianceCase;
use App\Models\TransactionIntent;
use App\Services\AuditService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SARReportGenerator
{
    protected AuditService $audit;

    public function __construct(AuditService $audit)
    {
        $this->audit = $audit;
    }

    public function generateSAR(ComplianceCase $case, User $filedBy): array
    {
        $sarReference = $this->generateSARReference();
        
        $reportData = [
            'sar_reference' => $sarReference,
            'filing_date' => now()->toIso8601String(),
            'filing_institution' => [
                'name' => config('app.name', 'PANÉTA'),
                'identifier' => config('paneta.institution_id', 'PANETA-001'),
                'country' => 'US',
            ],
            'subject' => $this->buildSubjectSection($case),
            'suspicious_activity' => $this->buildActivitySection($case),
            'narrative' => $this->buildNarrative($case),
            'supporting_documentation' => $this->collectSupportingDocs($case),
            'filed_by' => [
                'user_id' => $filedBy->id,
                'name' => $filedBy->name,
                'role' => $filedBy->role,
            ],
        ];

        // Update case with SAR reference
        $case->update([
            'sar_reference' => $sarReference,
            'status' => ComplianceCase::STATUS_SAR_FILED,
            'closed_at' => now(),
            'closed_by' => $filedBy->id,
        ]);

        // Store SAR document
        $this->storeSARDocument($sarReference, $reportData);

        // Audit log
        $this->audit->log(
            $filedBy->id,
            'sar_filed',
            'compliance_case',
            $case->id,
            [
                'sar_reference' => $sarReference,
                'subject_user_id' => $case->user_id,
            ]
        );

        Log::info('SAR filed', [
            'sar_reference' => $sarReference,
            'case_reference' => $case->case_reference,
            'filed_by' => $filedBy->id,
        ]);

        return $reportData;
    }

    public function generateDraftSAR(ComplianceCase $case): array
    {
        return [
            'status' => 'draft',
            'generated_at' => now()->toIso8601String(),
            'case_reference' => $case->case_reference,
            'subject' => $this->buildSubjectSection($case),
            'suspicious_activity' => $this->buildActivitySection($case),
            'narrative' => $this->buildNarrative($case),
            'review_required' => true,
        ];
    }

    protected function buildSubjectSection(ComplianceCase $case): array
    {
        $user = $case->user;
        
        if (!$user) {
            return ['type' => 'unknown'];
        }

        return [
            'type' => 'individual',
            'name' => $user->name,
            'email' => $this->maskEmail($user->email),
            'date_of_birth' => $user->date_of_birth ?? null,
            'nationality' => $user->nationality ?? null,
            'kyc_status' => $user->kyc_status,
            'risk_tier' => $user->risk_tier ?? 'standard',
            'account_created' => $user->created_at->toIso8601String(),
            'identifiers' => [
                'internal_id' => $user->id,
            ],
        ];
    }

    protected function buildActivitySection(ComplianceCase $case): array
    {
        $activity = [
            'type' => $case->type,
            'date_detected' => $case->created_at->toIso8601String(),
            'amount_involved' => $case->amount_involved,
            'currency' => $case->currency,
            'description' => $case->description,
        ];

        // Get related transactions if available
        if ($case->related_type === TransactionIntent::class && $case->related) {
            $transaction = $case->related;
            $activity['related_transaction'] = [
                'id' => $transaction->id,
                'reference' => $transaction->reference ?? null,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at->toIso8601String(),
            ];
        }

        return $activity;
    }

    protected function buildNarrative(ComplianceCase $case): string
    {
        $narrative = [];

        $narrative[] = "Case Reference: {$case->case_reference}";
        $narrative[] = "Type: {$case->type}";
        $narrative[] = "Priority: {$case->priority}";
        $narrative[] = "Risk Level: {$case->risk_level}";
        $narrative[] = "";
        $narrative[] = "Description:";
        $narrative[] = $case->description;

        if ($case->investigation_notes) {
            $narrative[] = "";
            $narrative[] = "Investigation Notes:";
            $narrative[] = $case->investigation_notes;
        }

        // Add case notes
        $notes = $case->notes()->orderBy('created_at')->get();
        if ($notes->isNotEmpty()) {
            $narrative[] = "";
            $narrative[] = "Case Timeline:";
            foreach ($notes as $note) {
                $narrative[] = "[{$note->created_at->format('Y-m-d H:i')}] ({$note->note_type}) {$note->note}";
            }
        }

        return implode("\n", $narrative);
    }

    protected function collectSupportingDocs(ComplianceCase $case): array
    {
        $docs = [];

        // Add evidence references
        if (!empty($case->evidence_ids)) {
            foreach ($case->evidence_ids as $evidenceId) {
                $docs[] = [
                    'type' => 'evidence',
                    'reference' => $evidenceId,
                ];
            }
        }

        // Add related audit logs
        $docs[] = [
            'type' => 'audit_trail',
            'reference' => "audit_logs_case_{$case->id}",
        ];

        return $docs;
    }

    protected function storeSARDocument(string $reference, array $data): void
    {
        $filename = "compliance/sar/{$reference}.json";
        Storage::disk('local')->put($filename, json_encode($data, JSON_PRETTY_PRINT));
    }

    protected function generateSARReference(): string
    {
        return 'SAR-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***';
        }
        
        $name = $parts[0];
        $domain = $parts[1];
        
        $maskedName = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 2));
        
        return $maskedName . '@' . $domain;
    }

    public function getSARHistory(int $days = 90): array
    {
        $sars = ComplianceCase::where('status', ComplianceCase::STATUS_SAR_FILED)
            ->whereNotNull('sar_reference')
            ->where('closed_at', '>=', now()->subDays($days))
            ->orderBy('closed_at', 'desc')
            ->get();

        return [
            'period_days' => $days,
            'total_filed' => $sars->count(),
            'by_type' => $sars->groupBy('type')->map->count()->toArray(),
            'sars' => $sars->map(function ($case) {
                return [
                    'sar_reference' => $case->sar_reference,
                    'case_reference' => $case->case_reference,
                    'type' => $case->type,
                    'amount' => $case->amount_involved,
                    'currency' => $case->currency,
                    'filed_at' => $case->closed_at->toIso8601String(),
                ];
            })->toArray(),
        ];
    }

    public function getComplianceMetrics(int $days = 30): array
    {
        $since = now()->subDays($days);

        $cases = ComplianceCase::where('created_at', '>=', $since)->get();

        return [
            'period_days' => $days,
            'total_cases' => $cases->count(),
            'open_cases' => $cases->where('status', 'open')->count(),
            'sars_filed' => $cases->where('status', ComplianceCase::STATUS_SAR_FILED)->count(),
            'false_positives' => $cases->where('status', 'closed_false_positive')->count(),
            'avg_resolution_days' => $cases->whereNotNull('closed_at')
                ->avg(fn($c) => $c->created_at->diffInDays($c->closed_at)) ?? 0,
            'by_type' => $cases->groupBy('type')->map->count()->toArray(),
            'by_priority' => $cases->groupBy('priority')->map->count()->toArray(),
            'overdue_cases' => $cases->filter(fn($c) => $c->due_date && $c->due_date < now() && $c->isOpen())->count(),
        ];
    }
}

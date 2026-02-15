<?php

namespace App\Services;

use App\Models\CrossBorderTransactionIntent;
use App\Models\TransactionIntent;
use App\Models\PaymentInstruction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconciliationEngine
{
    const TIMEOUT_MINUTES = 30;

    public function __construct(
        protected AuditService $auditService
    ) {}

    public function reconcileTransaction(TransactionIntent $intent): ReconciliationResult
    {
        $instruction = $intent->paymentInstruction;
        
        if (!$instruction) {
            return new ReconciliationResult(
                reconciled: false,
                reason: 'No payment instruction found'
            );
        }

        $verificationResult = $this->verifyInstructionIntegrity($instruction);
        
        if (!$verificationResult['valid']) {
            return new ReconciliationResult(
                reconciled: false,
                reason: $verificationResult['reason'],
                requiresReview: true
            );
        }

        $statusMatch = $this->verifyStatusConsistency($intent, $instruction);
        
        if (!$statusMatch['consistent']) {
            return new ReconciliationResult(
                reconciled: false,
                reason: $statusMatch['reason'],
                requiresReview: true
            );
        }

        return new ReconciliationResult(
            reconciled: true,
            reason: 'All checks passed'
        );
    }

    public function reconcileCrossBorderTransaction(CrossBorderTransactionIntent $intent): ReconciliationResult
    {
        $legStatuses = $intent->leg_statuses ?? [];
        $expectedLegs = ['fx_quote', 'source_debit', 'fx_conversion', 'destination_credit'];
        $completedLegs = array_keys(array_filter($legStatuses, fn($leg) => ($leg['status'] ?? '') === 'completed'));

        if ($intent->status === 'completed' && count($completedLegs) !== count($expectedLegs)) {
            return new ReconciliationResult(
                reconciled: false,
                reason: 'Transaction marked complete but not all legs are completed',
                requiresReview: true,
                details: [
                    'expected_legs' => $expectedLegs,
                    'completed_legs' => $completedLegs,
                ]
            );
        }

        foreach ($legStatuses as $leg => $data) {
            if (($data['status'] ?? '') === 'failed' && $intent->status !== 'failed') {
                return new ReconciliationResult(
                    reconciled: false,
                    reason: "Leg '{$leg}' failed but transaction not marked as failed",
                    requiresReview: true
                );
            }
        }

        return new ReconciliationResult(
            reconciled: true,
            reason: 'All legs verified'
        );
    }

    public function detectTimeouts(): Collection
    {
        $cutoff = now()->subMinutes(self::TIMEOUT_MINUTES);

        $timedOutLocal = TransactionIntent::where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->get();

        $timedOutCrossBorder = CrossBorderTransactionIntent::whereIn('status', [
            'pending', 'fx_locked', 'source_debited', 'fx_executed'
        ])
            ->where('created_at', '<', $cutoff)
            ->get();

        return $timedOutLocal->concat($timedOutCrossBorder);
    }

    public function handleTimeout($transaction): void
    {
        Log::warning('Transaction timeout detected', [
            'type' => get_class($transaction),
            'id' => $transaction->id,
            'status' => $transaction->status,
            'created_at' => $transaction->created_at,
        ]);

        if ($transaction instanceof CrossBorderTransactionIntent) {
            $this->rollbackCrossBorderTransaction($transaction);
        } else {
            $transaction->update(['status' => 'failed']);
        }

        $this->auditService->log(
            $transaction->user_id,
            'transaction_timeout',
            get_class($transaction),
            $transaction->id,
            ['original_status' => $transaction->status]
        );
    }

    protected function rollbackCrossBorderTransaction(CrossBorderTransactionIntent $intent): void
    {
        DB::transaction(function () use ($intent) {
            if (in_array($intent->status, ['source_debited', 'fx_executed', 'destination_credited'])) {
                $sourceAccount = $intent->sourceAccount;
                if ($sourceAccount) {
                    $sourceAccount->increment('mock_balance', $intent->source_amount + $intent->fee_amount);
                }
            }

            $intent->update([
                'status' => 'rolled_back',
                'failure_reason' => 'Transaction timeout - automatic rollback',
            ]);
        });

        $this->auditService->log(
            $intent->user_id,
            'cross_border_rollback',
            CrossBorderTransactionIntent::class,
            $intent->id,
            ['reason' => 'timeout']
        );
    }

    protected function verifyInstructionIntegrity(PaymentInstruction $instruction): array
    {
        $expectedHash = PaymentInstruction::generateSignedHash($instruction->instruction_payload);
        
        if (!hash_equals($expectedHash, $instruction->signed_hash)) {
            return [
                'valid' => false,
                'reason' => 'Instruction signature mismatch - possible tampering',
            ];
        }

        return ['valid' => true];
    }

    protected function verifyStatusConsistency(TransactionIntent $intent, PaymentInstruction $instruction): array
    {
        $validCombinations = [
            'pending' => ['generated'],
            'confirmed' => ['generated', 'sent'],
            'executed' => ['acknowledged'],
            'failed' => ['generated', 'sent', 'failed'],
        ];

        $allowedInstructionStatuses = $validCombinations[$intent->status] ?? [];

        if (!in_array($instruction->status, $allowedInstructionStatuses)) {
            return [
                'consistent' => false,
                'reason' => "Intent status '{$intent->status}' inconsistent with instruction status '{$instruction->status}'",
            ];
        }

        return ['consistent' => true];
    }

    public function getReconciliationReport(): array
    {
        $localTransactions = TransactionIntent::whereDate('created_at', today())->get();
        $crossBorderTransactions = CrossBorderTransactionIntent::whereDate('created_at', today())->get();

        $localReconciled = 0;
        $localFailed = 0;
        $crossBorderReconciled = 0;
        $crossBorderFailed = 0;

        foreach ($localTransactions as $tx) {
            $result = $this->reconcileTransaction($tx);
            $result->reconciled ? $localReconciled++ : $localFailed++;
        }

        foreach ($crossBorderTransactions as $tx) {
            $result = $this->reconcileCrossBorderTransaction($tx);
            $result->reconciled ? $crossBorderReconciled++ : $crossBorderFailed++;
        }

        return [
            'date' => today()->toDateString(),
            'local_transactions' => [
                'total' => $localTransactions->count(),
                'reconciled' => $localReconciled,
                'failed' => $localFailed,
            ],
            'cross_border_transactions' => [
                'total' => $crossBorderTransactions->count(),
                'reconciled' => $crossBorderReconciled,
                'failed' => $crossBorderFailed,
            ],
            'timeouts_detected' => $this->detectTimeouts()->count(),
        ];
    }
}

class ReconciliationResult
{
    public function __construct(
        public bool $reconciled,
        public string $reason,
        public bool $requiresReview = false,
        public array $details = []
    ) {}
}

<?php

namespace App\Services;

use App\Models\PaymentInstruction;
use App\Models\TransactionIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ExecutionFailureHandler
 * 
 * Handles transaction execution failures:
 * - Automated reversal orchestration
 * - Atomic rollback enforcement
 * - Partial failure detection
 * - Reconciliation of rollbacks
 */
class ExecutionFailureHandler
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly DigitalInstructionSigningService $signingService
    ) {}

    /**
     * Detect partial failure in multi-leg transaction
     */
    public function detectPartialFailure(TransactionIntent $transaction): array
    {
        $instructions = $transaction->paymentInstructions ?? collect([$transaction->paymentInstruction]);
        
        $executedLegs = [];
        $failedLegs = [];
        $pendingLegs = [];

        foreach ($instructions as $instruction) {
            if (!$instruction) continue;

            switch ($instruction->status) {
                case 'executed':
                case 'completed':
                    $executedLegs[] = $instruction->id;
                    break;
                case 'failed':
                case 'rejected':
                    $failedLegs[] = $instruction->id;
                    break;
                default:
                    $pendingLegs[] = $instruction->id;
            }
        }

        $isPartialFailure = !empty($executedLegs) && !empty($failedLegs);

        $result = [
            'transaction_id' => $transaction->id,
            'is_partial_failure' => $isPartialFailure,
            'executed_legs' => $executedLegs,
            'failed_legs' => $failedLegs,
            'pending_legs' => $pendingLegs,
            'requires_reversal' => $isPartialFailure,
            'detected_at' => now()->toIso8601String(),
        ];

        if ($isPartialFailure) {
            $this->auditService->log(
                'partial_failure_detected',
                'transaction_intent',
                $transaction->id,
                null,
                $result
            );

            Log::warning('Partial transaction failure detected', [
                'transaction_id' => $transaction->id,
                'executed_legs' => count($executedLegs),
                'failed_legs' => count($failedLegs),
            ]);
        }

        return $result;
    }

    /**
     * Trigger reversal instruction for executed legs
     */
    public function triggerReversalInstruction(TransactionIntent $transaction): array
    {
        $failureAnalysis = $this->detectPartialFailure($transaction);

        if (!$failureAnalysis['requires_reversal']) {
            return [
                'reversal_triggered' => false,
                'reason' => 'No reversal required',
            ];
        }

        return DB::transaction(function () use ($transaction, $failureAnalysis) {
            $reversalInstructions = [];

            foreach ($failureAnalysis['executed_legs'] as $legId) {
                $originalInstruction = PaymentInstruction::find($legId);
                
                if (!$originalInstruction) {
                    continue;
                }

                $reversalInstruction = $this->createReversalInstruction($originalInstruction);
                $reversalInstructions[] = $reversalInstruction;
            }

            $transaction->update([
                'status' => 'reversal_pending',
                'reversal_initiated_at' => now(),
            ]);

            $this->auditService->log(
                'reversal_instructions_created',
                'transaction_intent',
                $transaction->id,
                null,
                [
                    'original_legs' => $failureAnalysis['executed_legs'],
                    'reversal_count' => count($reversalInstructions),
                    'reversal_ids' => collect($reversalInstructions)->pluck('id')->toArray(),
                ]
            );

            return [
                'reversal_triggered' => true,
                'reversal_instructions' => $reversalInstructions,
                'transaction_status' => 'reversal_pending',
            ];
        });
    }

    /**
     * Reconcile rollback and finalize
     */
    public function reconcileRollback(TransactionIntent $transaction): array
    {
        $reversalInstructions = PaymentInstruction::where('original_instruction_id', '!=', null)
            ->whereHas('transactionIntent', fn ($q) => $q->where('id', $transaction->id))
            ->get();

        $allReversed = $reversalInstructions->every(fn ($i) => $i->status === 'executed');
        $anyFailed = $reversalInstructions->contains(fn ($i) => $i->status === 'failed');

        $result = [
            'transaction_id' => $transaction->id,
            'reversal_count' => $reversalInstructions->count(),
            'all_reversed' => $allReversed,
            'any_failed' => $anyFailed,
            'reconciled_at' => now()->toIso8601String(),
        ];

        if ($allReversed) {
            $transaction->update([
                'status' => 'rolled_back',
                'rollback_completed_at' => now(),
            ]);
            $result['final_status'] = 'rolled_back';
        } elseif ($anyFailed) {
            $transaction->update([
                'status' => 'reversal_failed',
                'requires_manual_intervention' => true,
            ]);
            $result['final_status'] = 'reversal_failed';
            $result['requires_manual_intervention'] = true;

            Log::critical('Transaction reversal failed - manual intervention required', [
                'transaction_id' => $transaction->id,
            ]);
        }

        $this->auditService->log(
            'rollback_reconciled',
            'transaction_intent',
            $transaction->id,
            null,
            $result
        );

        return $result;
    }

    /**
     * Handle complete transaction failure
     */
    public function handleCompleteFailure(
        TransactionIntent $transaction,
        string $failureReason
    ): array {
        $transaction->update([
            'status' => 'failed',
            'failure_reason' => $failureReason,
            'failed_at' => now(),
        ]);

        $this->auditService->log(
            'transaction_failed',
            'transaction_intent',
            $transaction->id,
            null,
            [
                'failure_reason' => $failureReason,
                'failed_at' => now()->toIso8601String(),
            ]
        );

        return [
            'transaction_id' => $transaction->id,
            'status' => 'failed',
            'failure_reason' => $failureReason,
            'requires_reversal' => false,
        ];
    }

    /**
     * Get failure report for transaction
     */
    public function getFailureReport(TransactionIntent $transaction): array
    {
        $failureAnalysis = $this->detectPartialFailure($transaction);

        return [
            'transaction_id' => $transaction->id,
            'status' => $transaction->status,
            'failure_type' => $failureAnalysis['is_partial_failure'] ? 'partial' : 'complete',
            'failure_analysis' => $failureAnalysis,
            'failure_reason' => $transaction->failure_reason,
            'failed_at' => $transaction->failed_at,
            'reversal_status' => $transaction->reversal_initiated_at ? 'initiated' : 'not_initiated',
            'rollback_completed' => $transaction->rollback_completed_at !== null,
            'requires_manual_intervention' => $transaction->requires_manual_intervention ?? false,
        ];
    }

    /**
     * Create reversal instruction for an executed instruction
     */
    private function createReversalInstruction(PaymentInstruction $original): PaymentInstruction
    {
        $reversalData = [
            'transaction_intent_id' => $original->transaction_intent_id,
            'issuer_institution_id' => $original->acquirer_institution_id,
            'acquirer_institution_id' => $original->issuer_institution_id,
            'amount' => $original->amount,
            'currency' => $original->currency,
            'status' => 'pending',
            'instruction_type' => 'reversal',
            'original_instruction_id' => $original->id,
            'metadata' => [
                'reversal_of' => $original->id,
                'original_executed_at' => $original->executed_at,
                'reversal_reason' => 'Partial transaction failure - atomic rollback',
            ],
        ];

        $reversal = PaymentInstruction::create($reversalData);

        $this->auditService->log(
            'reversal_instruction_created',
            'payment_instruction',
            $reversal->id,
            null,
            [
                'original_instruction_id' => $original->id,
                'amount' => $reversal->amount,
                'currency' => $reversal->currency,
            ]
        );

        return $reversal;
    }
}

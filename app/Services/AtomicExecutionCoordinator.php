<?php

namespace App\Services;

use App\Models\PaymentInstruction;
use App\Models\TransactionIntent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AtomicExecutionCoordinator
 * 
 * Enforces all-or-nothing execution for composite transactions:
 * - All-or-nothing execution guard
 * - Linked instruction barrier
 * - Dual-leg atomic enforcement
 * - Rollback on partial failure
 */
class AtomicExecutionCoordinator
{
    private array $registeredInstructions = [];
    private string $coordinationId;

    public function __construct(
        private readonly AuditService $auditService,
        private readonly DigitalInstructionSigningService $signingService
    ) {
        $this->coordinationId = Str::uuid()->toString();
    }

    /**
     * Register linked instructions for atomic execution
     */
    public function registerLinkedInstructions(array $instructions): string
    {
        $this->registeredInstructions = [];

        foreach ($instructions as $instruction) {
            $this->registeredInstructions[] = [
                'instruction' => $instruction,
                'preconditions_met' => false,
                'execution_status' => 'pending',
                'execution_result' => null,
            ];
        }

        $this->auditService->log(
            'atomic_execution_registered',
            'coordination',
            $this->coordinationId,
            null,
            [
                'instruction_count' => count($instructions),
                'instruction_ids' => collect($instructions)->pluck('id')->toArray(),
            ]
        );

        return $this->coordinationId;
    }

    /**
     * Validate all preconditions before execution
     */
    public function validateAllPreconditions(): array
    {
        $results = [];
        $allPassed = true;

        foreach ($this->registeredInstructions as $index => &$entry) {
            $instruction = $entry['instruction'];
            $preconditionResult = $this->validateInstructionPreconditions($instruction);
            
            $entry['preconditions_met'] = $preconditionResult['passed'];
            $results[$index] = $preconditionResult;

            if (!$preconditionResult['passed']) {
                $allPassed = false;
            }
        }

        $this->auditService->log(
            'atomic_preconditions_validated',
            'coordination',
            $this->coordinationId,
            null,
            [
                'all_passed' => $allPassed,
                'results' => $results,
            ]
        );

        return [
            'all_passed' => $allPassed,
            'results' => $results,
        ];
    }

    /**
     * Dispatch atomic instructions - all or nothing
     */
    public function dispatchAtomicInstructions(callable $executor): array
    {
        $preconditionCheck = $this->validateAllPreconditions();

        if (!$preconditionCheck['all_passed']) {
            return [
                'success' => false,
                'error' => 'Precondition validation failed',
                'details' => $preconditionCheck['results'],
            ];
        }

        return DB::transaction(function () use ($executor) {
            $executionResults = [];
            $allSucceeded = true;

            foreach ($this->registeredInstructions as $index => &$entry) {
                try {
                    $result = $executor($entry['instruction'], $index);
                    $entry['execution_status'] = $result['success'] ? 'completed' : 'failed';
                    $entry['execution_result'] = $result;
                    $executionResults[$index] = $result;

                    if (!$result['success']) {
                        $allSucceeded = false;
                        break;
                    }
                } catch (\Exception $e) {
                    $entry['execution_status'] = 'failed';
                    $entry['execution_result'] = ['error' => $e->getMessage()];
                    $executionResults[$index] = ['success' => false, 'error' => $e->getMessage()];
                    $allSucceeded = false;
                    break;
                }
            }

            if (!$allSucceeded) {
                $this->abortIfAnyLegFails();
                throw new \RuntimeException('Atomic execution failed - rolling back all instructions');
            }

            $this->auditService->log(
                'atomic_execution_completed',
                'coordination',
                $this->coordinationId,
                null,
                [
                    'success' => true,
                    'instructions_executed' => count($this->registeredInstructions),
                ]
            );

            return [
                'success' => true,
                'coordination_id' => $this->coordinationId,
                'results' => $executionResults,
            ];
        });
    }

    /**
     * Abort execution if any leg fails
     */
    public function abortIfAnyLegFails(): void
    {
        $this->auditService->log(
            'atomic_execution_aborted',
            'coordination',
            $this->coordinationId,
            null,
            [
                'reason' => 'One or more instruction legs failed',
                'instruction_states' => collect($this->registeredInstructions)
                    ->map(fn ($entry) => [
                        'status' => $entry['execution_status'],
                        'result' => $entry['execution_result'],
                    ])->toArray(),
            ]
        );

        foreach ($this->registeredInstructions as &$entry) {
            if ($entry['execution_status'] === 'completed') {
                $entry['execution_status'] = 'rolled_back';
            }
        }
    }

    /**
     * Execute dual-leg transaction atomically
     */
    public function executeDualLeg(
        PaymentInstruction $debitInstruction,
        PaymentInstruction $creditInstruction,
        callable $debitExecutor,
        callable $creditExecutor
    ): array {
        $this->registerLinkedInstructions([
            ['type' => 'debit', 'instruction' => $debitInstruction],
            ['type' => 'credit', 'instruction' => $creditInstruction],
        ]);

        return $this->dispatchAtomicInstructions(function ($entry, $index) use ($debitExecutor, $creditExecutor) {
            if ($entry['type'] === 'debit') {
                return $debitExecutor($entry['instruction']);
            } else {
                return $creditExecutor($entry['instruction']);
            }
        });
    }

    /**
     * Validate preconditions for a single instruction
     */
    private function validateInstructionPreconditions(array|PaymentInstruction $instruction): array
    {
        $checks = [];

        if ($instruction instanceof PaymentInstruction) {
            $checks['has_valid_amount'] = $instruction->amount > 0;
            $checks['has_currency'] = !empty($instruction->currency);
            $checks['has_issuer'] = !empty($instruction->issuer_institution_id);
            $checks['not_already_executed'] = $instruction->status !== 'executed';
            $checks['not_cancelled'] = $instruction->status !== 'cancelled';
        } else {
            $checks['is_array'] = is_array($instruction);
            $checks['has_required_fields'] = isset($instruction['instruction']) || isset($instruction['type']);
        }

        $passed = !in_array(false, $checks, true);

        return [
            'passed' => $passed,
            'checks' => $checks,
        ];
    }

    /**
     * Get coordination ID
     */
    public function getCoordinationId(): string
    {
        return $this->coordinationId;
    }
}

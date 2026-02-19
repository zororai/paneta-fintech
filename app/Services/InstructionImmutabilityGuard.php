<?php

namespace App\Services;

use App\Models\PaymentInstruction;
use Illuminate\Support\Facades\Log;

/**
 * InstructionImmutabilityGuard
 * 
 * Enforces payment instruction immutability:
 * - Instruction mutation lock
 * - Versioning control
 * - Post-signature modification prevention
 */
class InstructionImmutabilityGuard
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Lock instruction to prevent modifications
     */
    public function lockInstruction(PaymentInstruction $instruction): PaymentInstruction
    {
        if ($instruction->is_locked) {
            throw new \RuntimeException('Instruction is already locked');
        }

        $instruction->update([
            'is_locked' => true,
            'locked_at' => now(),
            'lock_version' => ($instruction->lock_version ?? 0) + 1,
        ]);

        $this->auditService->log(
            'instruction_locked',
            'payment_instruction',
            $instruction->id,
            null,
            [
                'locked_at' => now()->toIso8601String(),
                'version' => $instruction->lock_version,
            ]
        );

        return $instruction->fresh();
    }

    /**
     * Prevent any modification after instruction is signed
     */
    public function preventPostSignatureModification(
        PaymentInstruction $instruction,
        array $proposedChanges
    ): void {
        if ($instruction->instruction_signature) {
            $this->auditService->log(
                'post_signature_modification_blocked',
                'payment_instruction',
                $instruction->id,
                null,
                [
                    'proposed_changes' => array_keys($proposedChanges),
                    'blocked_at' => now()->toIso8601String(),
                ]
            );

            throw new \RuntimeException(
                'Cannot modify instruction after digital signature has been applied. ' .
                'Create a new instruction version instead.'
            );
        }

        if ($instruction->is_locked) {
            $this->auditService->log(
                'locked_instruction_modification_blocked',
                'payment_instruction',
                $instruction->id,
                null,
                [
                    'proposed_changes' => array_keys($proposedChanges),
                ]
            );

            throw new \RuntimeException(
                'Cannot modify locked instruction. Create a new version if changes are required.'
            );
        }
    }

    /**
     * Create a new version of an instruction
     */
    public function createInstructionVersion(
        PaymentInstruction $original,
        array $modifications
    ): PaymentInstruction {
        $originalVersion = $original->version ?? 1;
        $newVersion = $originalVersion + 1;

        $newData = array_merge(
            $original->toArray(),
            $modifications,
            [
                'id' => null,
                'version' => $newVersion,
                'parent_instruction_id' => $original->id,
                'is_locked' => false,
                'locked_at' => null,
                'instruction_signature' => null,
                'instruction_hash' => null,
                'sealed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        unset($newData['id']);

        $newInstruction = PaymentInstruction::create($newData);

        $original->update([
            'superseded_by' => $newInstruction->id,
            'superseded_at' => now(),
        ]);

        $this->auditService->log(
            'instruction_version_created',
            'payment_instruction',
            $newInstruction->id,
            null,
            [
                'original_id' => $original->id,
                'original_version' => $originalVersion,
                'new_version' => $newVersion,
                'modifications' => array_keys($modifications),
            ]
        );

        return $newInstruction;
    }

    /**
     * Validate instruction has not been tampered with
     */
    public function validateIntegrity(PaymentInstruction $instruction): array
    {
        $issues = [];

        if ($instruction->is_locked && !$instruction->locked_at) {
            $issues[] = 'Lock timestamp missing';
        }

        if ($instruction->instruction_signature && !$instruction->instruction_hash) {
            $issues[] = 'Signature present but hash missing';
        }

        if ($instruction->sealed_at && !$instruction->instruction_signature) {
            $issues[] = 'Sealed but no signature';
        }

        if ($instruction->superseded_by && !$instruction->superseded_at) {
            $issues[] = 'Superseded reference but no timestamp';
        }

        return [
            'instruction_id' => $instruction->id,
            'integrity_valid' => empty($issues),
            'issues' => $issues,
            'is_locked' => $instruction->is_locked,
            'is_signed' => !empty($instruction->instruction_signature),
            'is_sealed' => !empty($instruction->sealed_at),
            'version' => $instruction->version ?? 1,
            'validated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get instruction version history
     */
    public function getVersionHistory(PaymentInstruction $instruction): array
    {
        $history = [];
        $current = $instruction;

        $history[] = $this->formatVersionEntry($current);

        while ($current->parent_instruction_id) {
            $parent = PaymentInstruction::find($current->parent_instruction_id);
            if (!$parent) break;
            
            $history[] = $this->formatVersionEntry($parent);
            $current = $parent;
        }

        return array_reverse($history);
    }

    /**
     * Format version entry
     */
    private function formatVersionEntry(PaymentInstruction $instruction): array
    {
        return [
            'id' => $instruction->id,
            'version' => $instruction->version ?? 1,
            'amount' => $instruction->amount,
            'currency' => $instruction->currency,
            'status' => $instruction->status,
            'is_locked' => $instruction->is_locked,
            'is_current' => !$instruction->superseded_by,
            'created_at' => $instruction->created_at->toIso8601String(),
        ];
    }
}

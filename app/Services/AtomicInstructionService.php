<?php

namespace App\Services;

use App\Models\EscrowTransaction;
use App\Models\AtomicInstruction;
use Illuminate\Support\Facades\Log;

class AtomicInstructionService
{
    /**
     * Generate atomic instructions for both parties
     */
    public function generateInstructions(EscrowTransaction $escrow): array
    {
        try {
            $exchangeRequest = $escrow->exchangeRequest()->with([
                'offer.user',
                'offer.sourceAccount',
                'offer.destinationAccount',
                'counterparty',
                'counterpartySourceAccount',
                'counterpartyDestinationAccount'
            ])->first();

            // Generate instruction for initiator
            $initiatorInstruction = $this->createInstruction(
                $escrow,
                $escrow->initiator_user_id,
                $escrow->init_source_acct_id,
                $escrow->cp_dest_acct_id,
                'initiator_send',
                $escrow->initiator_currency,
                $escrow->initiator_amount,
                $escrow->initiator_fee,
                $escrow->initiator_total,
                $exchangeRequest->counterparty
            );

            // Generate instruction for counterparty
            $counterpartyInstruction = $this->createInstruction(
                $escrow,
                $escrow->counterparty_user_id,
                $escrow->cp_source_acct_id,
                $escrow->init_dest_acct_id,
                'counterparty_send',
                $escrow->counterparty_currency,
                $escrow->counterparty_amount,
                $escrow->counterparty_fee,
                $escrow->counterparty_total,
                $escrow->initiator
            );

            return [
                'success' => true,
                'initiator_instruction' => $initiatorInstruction,
                'counterparty_instruction' => $counterpartyInstruction,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to generate atomic instructions', [
                'escrow_id' => $escrow->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a single atomic instruction
     */
    private function createInstruction(
        EscrowTransaction $escrow,
        int $userId,
        int $sourceAccountId,
        int $destinationAccountId,
        string $instructionType,
        string $currency,
        float $amount,
        float $fee,
        float $totalAmount,
        $beneficiary
    ): AtomicInstruction {
        $payload = [
            'transaction_type' => 'p2p_exchange',
            'exchange_id' => $escrow->id,
            'timestamp' => now()->toIso8601String(),
            'beneficiary' => [
                'name' => $beneficiary->name,
                'user_id' => $beneficiary->id,
            ],
            'amount' => $amount,
            'fee' => $fee,
            'total' => $totalAmount,
            'currency' => $currency,
        ];

        $signedHash = $this->generateSignature($payload);

        return AtomicInstruction::create([
            'escrow_transaction_id' => $escrow->id,
            'user_id' => $userId,
            'source_account_id' => $sourceAccountId,
            'destination_account_id' => $destinationAccountId,
            'instruction_type' => $instructionType,
            'currency' => $currency,
            'amount' => $amount,
            'fee' => $fee,
            'total_amount' => $totalAmount,
            'instruction_payload' => $payload,
            'signed_hash' => $signedHash,
            'status' => 'generated',
        ]);
    }

    /**
     * Generate cryptographic signature for instruction
     */
    private function generateSignature(array $payload): string
    {
        // In production, use proper cryptographic signing
        // For now, generate a hash
        return hash('sha256', json_encode($payload) . config('app.key'));
    }

    /**
     * Send instructions to underlying institutions
     */
    public function sendToInstitutions(EscrowTransaction $escrow): bool
    {
        try {
            $instructions = $escrow->atomicInstructions;

            foreach ($instructions as $instruction) {
                // In production, send to actual institution APIs
                // For now, simulate sending
                $instruction->update([
                    'status' => 'sent_to_institution',
                    'sent_at' => now(),
                ]);

                Log::info('Atomic instruction sent to institution', [
                    'instruction_id' => $instruction->id,
                    'user_id' => $instruction->user_id,
                    'amount' => $instruction->total_amount,
                    'currency' => $instruction->currency,
                ]);
            }

            // Update escrow status
            $escrow->update(['status' => 'executing']);

            // Simulate settlement (in production, this would be async)
            $this->simulateSettlement($escrow);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send instructions to institutions', [
                'escrow_id' => $escrow->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Simulate settlement process
     */
    private function simulateSettlement(EscrowTransaction $escrow): void
    {
        // In production, this would be handled by institution callbacks
        // For demo purposes, we'll simulate immediate settlement
        
        $instructions = $escrow->atomicInstructions;

        foreach ($instructions as $instruction) {
            $instruction->update([
                'status' => 'settled',
                'executed_at' => now(),
                'settled_at' => now(),
                'institution_response' => 'Settlement successful',
            ]);
        }

        // Update escrow to completed
        $escrow->update(['status' => 'completed']);

        // Update exchange request to completed
        $escrow->exchangeRequest->update(['status' => 'completed']);

        Log::info('P2P Exchange completed successfully', [
            'escrow_id' => $escrow->id,
            'exchange_request_id' => $escrow->exchange_request_id,
        ]);
    }
}

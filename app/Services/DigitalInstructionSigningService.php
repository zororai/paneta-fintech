<?php

namespace App\Services;

use App\Models\PaymentInstruction;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DigitalInstructionSigningService
 * 
 * Provides mandatory digital signing of all Payment Instructions to ensure:
 * - Immutable Instruction ID generation
 * - Instruction hash storage
 * - Signature verification logging
 * - Zero-custody compliance through instruction-only model
 */
class DigitalInstructionSigningService
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly KeyManagementService $keyManagementService
    ) {}

    /**
     * Sign a payment instruction with digital signature
     */
    public function signInstruction(array $instructionPayload, User $user): array
    {
        $instructionId = $this->generateImmutableInstructionId();
        $timestamp = now()->toIso8601String();
        
        $payloadWithMetadata = array_merge($instructionPayload, [
            'instruction_id' => $instructionId,
            'timestamp' => $timestamp,
            'user_id' => $user->id,
            'nonce' => Str::random(32),
        ]);

        $hash = $this->generateInstructionHash($payloadWithMetadata);
        $signature = $this->createSignature($hash);

        $signedInstruction = [
            'payload' => $payloadWithMetadata,
            'hash' => $hash,
            'signature' => $signature,
            'signed_at' => $timestamp,
            'signer_id' => $user->id,
        ];

        $this->auditService->log(
            'instruction_signed',
            'payment_instruction',
            $instructionId,
            $user,
            [
                'hash' => $hash,
                'signature_prefix' => substr($signature, 0, 16) . '...',
            ]
        );

        return $signedInstruction;
    }

    /**
     * Verify a signed instruction
     */
    public function verifyInstruction(string $signature, array $payload): bool
    {
        $expectedHash = $this->generateInstructionHash($payload);
        $isValid = $this->verifySignature($signature, $expectedHash);

        $this->auditService->log(
            'instruction_verification',
            'payment_instruction',
            $payload['instruction_id'] ?? 'unknown',
            null,
            [
                'verification_result' => $isValid ? 'valid' : 'invalid',
                'hash' => $expectedHash,
            ]
        );

        return $isValid;
    }

    /**
     * Generate immutable instruction hash
     */
    public function generateInstructionHash(array $payload): string
    {
        $canonicalPayload = $this->canonicalizePayload($payload);
        return hash('sha256', $canonicalPayload);
    }

    /**
     * Seal instruction record - makes it immutable
     */
    public function sealInstructionRecord(PaymentInstruction $instruction): PaymentInstruction
    {
        if ($instruction->sealed_at !== null) {
            throw new \RuntimeException('Instruction already sealed and cannot be modified');
        }

        $payload = $instruction->toArray();
        $hash = $this->generateInstructionHash($payload);
        $signature = $this->createSignature($hash);

        $instruction->update([
            'instruction_hash' => $hash,
            'instruction_signature' => $signature,
            'sealed_at' => now(),
        ]);

        $this->auditService->log(
            'instruction_sealed',
            'payment_instruction',
            $instruction->id,
            null,
            ['hash' => $hash]
        );

        return $instruction->fresh();
    }

    /**
     * Validate instruction has not been tampered with
     */
    public function validateInstructionIntegrity(PaymentInstruction $instruction): bool
    {
        if (!$instruction->instruction_hash || !$instruction->instruction_signature) {
            return false;
        }

        $currentHash = $this->generateInstructionHash(
            $instruction->only([
                'id', 'transaction_intent_id', 'issuer_institution_id',
                'acquirer_institution_id', 'amount', 'currency', 'status'
            ])
        );

        return $currentHash === $instruction->instruction_hash;
    }

    /**
     * Generate immutable instruction ID
     */
    private function generateImmutableInstructionId(): string
    {
        $timestamp = now()->format('YmdHis');
        $random = Str::upper(Str::random(8));
        return "INST-{$timestamp}-{$random}";
    }

    /**
     * Canonicalize payload for consistent hashing
     */
    private function canonicalizePayload(array $payload): string
    {
        ksort($payload);
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Create signature using platform key
     */
    private function createSignature(string $hash): string
    {
        $key = $this->keyManagementService->getSigningKey();
        return hash_hmac('sha256', $hash, $key);
    }

    /**
     * Verify signature against hash
     */
    private function verifySignature(string $signature, string $hash): bool
    {
        $expectedSignature = $this->createSignature($hash);
        return hash_equals($expectedSignature, $signature);
    }
}

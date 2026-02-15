<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditService
{
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?User $user = null,
        array $metadata = []
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    public function logAccountLinked(User $user, int $accountId, array $metadata = []): AuditLog
    {
        return $this->log('account_linked', 'linked_account', $accountId, $user, $metadata);
    }

    public function logAccountRevoked(User $user, int $accountId, array $metadata = []): AuditLog
    {
        return $this->log('account_revoked', 'linked_account', $accountId, $user, $metadata);
    }

    public function logTransactionCreated(User $user, int $transactionId, array $metadata = []): AuditLog
    {
        return $this->log('transaction_created', 'transaction_intent', $transactionId, $user, $metadata);
    }

    public function logInstructionGenerated(User $user, int $instructionId, array $metadata = []): AuditLog
    {
        return $this->log('instruction_generated', 'payment_instruction', $instructionId, $user, $metadata);
    }

    public function logExecutionSimulated(User $user, int $transactionId, array $metadata = []): AuditLog
    {
        return $this->log('execution_simulated', 'transaction_intent', $transactionId, $user, $metadata);
    }

    public function logTransactionCompleted(User $user, int $transactionId, array $metadata = []): AuditLog
    {
        return $this->log('transaction_completed', 'transaction_intent', $transactionId, $user, $metadata);
    }

    public function logTransactionFailed(User $user, int $transactionId, array $metadata = []): AuditLog
    {
        return $this->log('transaction_failed', 'transaction_intent', $transactionId, $user, $metadata);
    }

    public function logUserRegistered(User $user): AuditLog
    {
        return $this->log('user_registered', 'user', $user->id, $user, [
            'email' => $user->email,
        ]);
    }

    public function logUserLogin(User $user): AuditLog
    {
        return $this->log('user_login', 'user', $user->id, $user, [
            'ip' => request()->ip(),
        ]);
    }

    public function logKycStatusChanged(User $user, string $oldStatus, string $newStatus): AuditLog
    {
        return $this->log('kyc_status_changed', 'user', $user->id, $user, [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);
    }
}

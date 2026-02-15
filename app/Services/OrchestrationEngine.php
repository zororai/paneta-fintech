<?php

namespace App\Services;

use App\Models\LinkedAccount;
use App\Models\PaymentInstruction;
use App\Models\TransactionIntent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrchestrationEngine
{
    public function __construct(
        private readonly ComplianceEngine $complianceEngine,
        private readonly MockInstitutionService $mockInstitutionService,
        private readonly AuditService $auditService
    ) {}

    public function createTransactionIntent(
        User $user,
        LinkedAccount $issuerAccount,
        string $acquirerIdentifier,
        float $amount,
        string $currency
    ): TransactionIntentResult {
        // Validate ownership
        if ($issuerAccount->user_id !== $user->id) {
            return new TransactionIntentResult(
                success: false,
                intent: null,
                error: 'Account does not belong to user'
            );
        }

        // Run compliance checks
        $complianceResult = $this->complianceEngine->checkTransaction($user, $issuerAccount, $amount);
        
        if (!$complianceResult->passed) {
            return new TransactionIntentResult(
                success: false,
                intent: null,
                error: $complianceResult->failureReason,
                complianceChecks: $complianceResult->checks
            );
        }

        // Create transaction intent
        $intent = TransactionIntent::create([
            'user_id' => $user->id,
            'issuer_account_id' => $issuerAccount->id,
            'acquirer_identifier' => $acquirerIdentifier,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'reference' => TransactionIntent::generateReference(),
        ]);

        // Log audit
        $this->auditService->logTransactionCreated($user, $intent->id, [
            'amount' => $amount,
            'currency' => $currency,
            'issuer_account_id' => $issuerAccount->id,
            'acquirer_identifier' => $acquirerIdentifier,
            'compliance_checks' => $complianceResult->checks,
        ]);

        return new TransactionIntentResult(
            success: true,
            intent: $intent,
            complianceChecks: $complianceResult->checks
        );
    }

    public function confirmAndExecute(TransactionIntent $intent): ExecutionResult
    {
        return DB::transaction(function () use ($intent) {
            $user = $intent->user;
            $issuerAccount = $intent->issuerAccount;

            // Confirm intent
            $intent->update(['status' => 'confirmed']);

            // Generate payment instruction
            $instruction = $this->generatePaymentInstruction($intent);
            
            $this->auditService->logInstructionGenerated($user, $instruction->id, [
                'transaction_reference' => $intent->reference,
                'signed_hash' => $instruction->signed_hash,
            ]);

            // Simulate sending to institution
            $instruction->update(['status' => 'sent']);

            // Simulate execution
            $executionResult = $this->mockInstitutionService->simulatePaymentExecution($instruction);
            
            $this->auditService->logExecutionSimulated($user, $intent->id, [
                'execution_result' => $executionResult->toArray(),
            ]);

            if (!$executionResult->success) {
                $intent->update(['status' => 'failed']);
                $instruction->update(['status' => 'generated']); // Reset
                
                $this->auditService->logTransactionFailed($user, $intent->id, [
                    'error_code' => $executionResult->errorCode,
                    'error_message' => $executionResult->errorMessage,
                ]);

                return new ExecutionResult(
                    success: false,
                    intent: $intent->fresh(),
                    instruction: $instruction->fresh(),
                    error: $executionResult->errorMessage
                );
            }

            // Deduct mock balance
            $this->mockInstitutionService->simulateBalanceDeduction($issuerAccount, $intent->amount);

            // Mark as executed
            $intent->update(['status' => 'executed']);
            $instruction->update(['status' => 'confirmed']);

            $this->auditService->logTransactionCompleted($user, $intent->id, [
                'external_reference' => $executionResult->externalReference,
                'new_balance' => $issuerAccount->fresh()->mock_balance,
            ]);

            return new ExecutionResult(
                success: true,
                intent: $intent->fresh(),
                instruction: $instruction->fresh(),
                externalReference: $executionResult->externalReference
            );
        });
    }

    private function generatePaymentInstruction(TransactionIntent $intent): PaymentInstruction
    {
        $payload = [
            'issuer_identifier' => $intent->issuerAccount->account_identifier,
            'acquirer_identifier' => $intent->acquirer_identifier,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'transaction_reference' => $intent->reference,
            'timestamp' => now()->toIso8601String(),
            'compliance_metadata' => [
                'kyc_verified' => $intent->user->isKycVerified(),
                'risk_tier' => $intent->user->risk_tier,
            ],
        ];

        $signedHash = PaymentInstruction::generateSignedHash($payload);

        return PaymentInstruction::create([
            'transaction_intent_id' => $intent->id,
            'instruction_payload' => $payload,
            'signed_hash' => $signedHash,
            'status' => 'generated',
        ]);
    }

    public function getDashboardData(User $user): array
    {
        $accounts = $user->linkedAccounts()
            ->with('institution')
            ->where('status', 'active')
            ->get();

        $totalBalance = $accounts->sum('mock_balance');
        
        $accountsGroupedByCurrency = $accounts->groupBy('currency')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('mock_balance'),
            ];
        });

        $recentTransactions = $user->transactionIntents()
            ->with('issuerAccount.institution')
            ->latest()
            ->limit(5)
            ->get();

        return [
            'total_balance' => $totalBalance,
            'accounts' => $accounts,
            'accounts_by_currency' => $accountsGroupedByCurrency,
            'recent_transactions' => $recentTransactions,
            'last_refresh' => now()->toIso8601String(),
        ];
    }
}

class TransactionIntentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?TransactionIntent $intent,
        public readonly ?string $error = null,
        public readonly array $complianceChecks = []
    ) {}
}

class ExecutionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly TransactionIntent $intent,
        public readonly PaymentInstruction $instruction,
        public readonly ?string $error = null,
        public readonly ?string $externalReference = null
    ) {}
}

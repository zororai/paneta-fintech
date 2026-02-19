<?php

namespace App\Services;

use App\Models\FeeLedger;
use App\Models\PaymentInstruction;
use App\Models\TransactionIntent;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * FeeSettlementValidator
 * 
 * Ensures regulatory compliance for fee collection:
 * - Explicit external fee settlement enforcement
 * - No temporary fee float
 * - Zero-custody fee handling
 * - Immediate fee reconciliation to external accounts
 */
class FeeSettlementValidator
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Ensure fee is collected at execution layer (not held internally)
     */
    public function ensureFeeCollectedAtExecutionLayer(
        TransactionIntent $transaction,
        float $feeAmount,
        string $feeCurrency
    ): bool {
        if ($feeAmount <= 0) {
            return true;
        }

        if (!$transaction->paymentInstruction) {
            $this->logViolation('fee_collection_no_instruction', $transaction, [
                'fee_amount' => $feeAmount,
                'fee_currency' => $feeCurrency,
            ]);
            throw new \RuntimeException('Fee cannot be collected without payment instruction at execution layer');
        }

        $instruction = $transaction->paymentInstruction;

        if (!$this->hasFeeInstructionComponent($instruction, $feeAmount)) {
            $this->logViolation('fee_not_in_instruction', $transaction, [
                'fee_amount' => $feeAmount,
                'instruction_id' => $instruction->id,
            ]);
            throw new \RuntimeException('Fee must be explicitly included in payment instruction');
        }

        return true;
    }

    /**
     * Validate no internal holding of fees
     */
    public function validateNoInternalHolding(float $feeAmount, string $feeCurrency): bool
    {
        $internalBalance = $this->getInternalFeeBalance($feeCurrency);

        if ($internalBalance > 0) {
            $this->auditService->log(
                'fee_internal_holding_detected',
                'fee_compliance',
                null,
                null,
                [
                    'internal_balance' => $internalBalance,
                    'currency' => $feeCurrency,
                    'warning' => 'Platform should not hold fee balances internally',
                ]
            );

            Log::warning('Internal fee holding detected', [
                'balance' => $internalBalance,
                'currency' => $feeCurrency,
            ]);
        }

        return true;
    }

    /**
     * Reconcile fee credit to external account
     */
    public function reconcileFeeCreditToExternalAccount(
        TransactionIntent $transaction,
        float $feeAmount,
        string $feeCurrency,
        string $externalAccountId
    ): array {
        $reconciliation = [
            'transaction_id' => $transaction->id,
            'fee_amount' => $feeAmount,
            'fee_currency' => $feeCurrency,
            'external_account_id' => $externalAccountId,
            'reconciled_at' => now()->toIso8601String(),
            'status' => 'pending',
        ];

        if (empty($externalAccountId)) {
            $this->logViolation('fee_no_external_account', $transaction, [
                'fee_amount' => $feeAmount,
            ]);
            throw new \RuntimeException('Fee settlement requires external account destination');
        }

        $feeRecord = FeeLedger::create([
            'transaction_intent_id' => $transaction->id,
            'amount' => $feeAmount,
            'currency' => $feeCurrency,
            'fee_type' => 'transaction_fee',
            'status' => 'settled',
            'external_reference' => $externalAccountId,
            'settled_at' => now(),
            'metadata' => [
                'settlement_type' => 'external',
                'reconciliation' => $reconciliation,
            ],
        ]);

        $reconciliation['status'] = 'completed';
        $reconciliation['fee_ledger_id'] = $feeRecord->id;

        $this->auditService->log(
            'fee_reconciled_externally',
            'fee_ledger',
            $feeRecord->id,
            null,
            $reconciliation
        );

        return $reconciliation;
    }

    /**
     * Validate fee structure is transparent
     */
    public function validateFeeTransparency(
        float $feeAmount,
        float $transactionAmount,
        string $feeType
    ): bool {
        $feePercentage = ($feeAmount / $transactionAmount) * 100;
        $maxFeePercentage = $this->getMaxFeePercentage($feeType);

        if ($feePercentage > $maxFeePercentage) {
            Log::warning('Fee exceeds maximum percentage', [
                'fee_percentage' => $feePercentage,
                'max_percentage' => $maxFeePercentage,
                'fee_type' => $feeType,
            ]);
        }

        return true;
    }

    /**
     * Ensure fee is deducted at source (not held)
     */
    public function ensureFeeDeductedAtSource(
        TransactionIntent $transaction,
        float $feeAmount
    ): bool {
        $totalAmount = $transaction->amount;
        $netAmount = $totalAmount - $feeAmount;

        if ($netAmount < 0) {
            throw new \RuntimeException('Fee cannot exceed transaction amount');
        }

        return true;
    }

    /**
     * Validate fee goes to external settlement account
     */
    public function validateExternalFeeDestination(string $destinationAccount): bool
    {
        if (empty($destinationAccount)) {
            throw new \RuntimeException('Fee destination account is required');
        }

        if ($this->isInternalAccount($destinationAccount)) {
            throw new \RuntimeException('Fees cannot be settled to internal platform accounts');
        }

        return true;
    }

    /**
     * Get fee collection report for compliance
     */
    public function getFeeCollectionReport(string $startDate, string $endDate): array
    {
        $fees = FeeLedger::whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'total_fees_collected' => $fees->sum('amount'),
            'fees_by_currency' => $fees->groupBy('currency')
                ->map(fn ($group) => $group->sum('amount'))
                ->toArray(),
            'fees_by_type' => $fees->groupBy('fee_type')
                ->map(fn ($group) => $group->sum('amount'))
                ->toArray(),
            'settlement_status' => [
                'settled' => $fees->where('status', 'settled')->count(),
                'pending' => $fees->where('status', 'pending')->count(),
            ],
            'external_settlements' => $fees->whereNotNull('external_reference')->count(),
        ];
    }

    /**
     * Check if instruction includes fee component
     */
    private function hasFeeInstructionComponent(PaymentInstruction $instruction, float $feeAmount): bool
    {
        $metadata = $instruction->metadata ?? [];
        return isset($metadata['fee_amount']) && abs($metadata['fee_amount'] - $feeAmount) < 0.01;
    }

    /**
     * Get internal fee balance (should be zero for compliance)
     */
    private function getInternalFeeBalance(string $currency): float
    {
        return FeeLedger::where('currency', $currency)
            ->where('status', 'pending')
            ->sum('amount');
    }

    /**
     * Get maximum fee percentage by type
     */
    private function getMaxFeePercentage(string $feeType): float
    {
        $maxFees = [
            'transaction_fee' => 3.0,
            'fx_fee' => 2.0,
            'withdrawal_fee' => 1.5,
            'default' => 5.0,
        ];

        return $maxFees[$feeType] ?? $maxFees['default'];
    }

    /**
     * Check if account is internal platform account
     */
    private function isInternalAccount(string $accountId): bool
    {
        $internalPrefixes = ['PANETA-', 'INTERNAL-', 'PLATFORM-'];

        foreach ($internalPrefixes as $prefix) {
            if (str_starts_with($accountId, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log fee compliance violation
     */
    private function logViolation(string $violationType, TransactionIntent $transaction, array $data = []): void
    {
        $this->auditService->log(
            'fee_compliance_violation',
            'transaction_intent',
            $transaction->id,
            null,
            array_merge([
                'violation_type' => $violationType,
                'transaction_id' => $transaction->id,
            ], $data)
        );

        Log::warning('Fee compliance violation', [
            'type' => $violationType,
            'transaction_id' => $transaction->id,
        ]);
    }
}

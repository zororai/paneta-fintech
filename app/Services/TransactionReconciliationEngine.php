<?php

namespace App\Services;

use App\Models\PaymentInstruction;
use App\Models\TransactionIntent;
use App\Models\FxQuote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * TransactionReconciliationEngine
 * 
 * Provides multi-leg reconciliation validation:
 * - Debit/Credit validation
 * - FX execution validation
 * - Amount matching across legs
 * - Transaction finalization
 */
class TransactionReconciliationEngine
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Validate debit leg of transaction
     */
    public function validateDebit(TransactionIntent $transaction): array
    {
        $result = [
            'valid' => true,
            'checks' => [],
            'errors' => [],
        ];

        $result['checks']['has_amount'] = $transaction->amount > 0;
        if (!$result['checks']['has_amount']) {
            $result['errors'][] = 'Transaction amount must be positive';
        }

        $result['checks']['has_issuer_account'] = !empty($transaction->issuer_account_id);
        if (!$result['checks']['has_issuer_account']) {
            $result['errors'][] = 'Debit requires issuer account';
        }

        $result['checks']['has_currency'] = !empty($transaction->currency);
        if (!$result['checks']['has_currency']) {
            $result['errors'][] = 'Transaction currency is required';
        }

        if ($transaction->issuerAccount) {
            $result['checks']['account_active'] = $transaction->issuerAccount->status === 'active';
            if (!$result['checks']['account_active']) {
                $result['errors'][] = 'Issuer account is not active';
            }

            $result['checks']['currency_match'] = $transaction->currency === $transaction->issuerAccount->currency;
            if (!$result['checks']['currency_match']) {
                $result['errors'][] = 'Transaction currency does not match account currency';
            }
        }

        $result['valid'] = empty($result['errors']);

        $this->auditService->log(
            'reconciliation_debit_validated',
            'transaction_intent',
            $transaction->id,
            null,
            $result
        );

        return $result;
    }

    /**
     * Validate credit leg of transaction
     */
    public function validateCredit(TransactionIntent $transaction): array
    {
        $result = [
            'valid' => true,
            'checks' => [],
            'errors' => [],
        ];

        $result['checks']['has_acquirer'] = !empty($transaction->acquirer_identifier);
        if (!$result['checks']['has_acquirer']) {
            $result['errors'][] = 'Credit requires acquirer identifier';
        }

        $instruction = $transaction->paymentInstruction;
        if ($instruction) {
            $result['checks']['instruction_amount_matches'] = 
                abs($instruction->amount - $transaction->amount) < 0.01;
            if (!$result['checks']['instruction_amount_matches']) {
                $result['errors'][] = 'Instruction amount does not match transaction amount';
            }

            $result['checks']['instruction_has_acquirer'] = !empty($instruction->acquirer_institution_id);
            if (!$result['checks']['instruction_has_acquirer']) {
                $result['errors'][] = 'Payment instruction missing acquirer institution';
            }
        } else {
            $result['checks']['has_instruction'] = false;
            $result['errors'][] = 'No payment instruction found';
        }

        $result['valid'] = empty($result['errors']);

        $this->auditService->log(
            'reconciliation_credit_validated',
            'transaction_intent',
            $transaction->id,
            null,
            $result
        );

        return $result;
    }

    /**
     * Validate FX execution if applicable
     */
    public function validateFXExecution(TransactionIntent $transaction, ?FxQuote $quote = null): array
    {
        $result = [
            'valid' => true,
            'checks' => [],
            'errors' => [],
            'fx_applied' => false,
        ];

        if (!$quote) {
            $result['checks']['fx_required'] = false;
            return $result;
        }

        $result['fx_applied'] = true;

        $result['checks']['quote_not_expired'] = !$quote->expires_at || $quote->expires_at->isFuture();
        if (!$result['checks']['quote_not_expired']) {
            $result['errors'][] = 'FX quote has expired';
        }

        $result['checks']['quote_status_valid'] = in_array($quote->status, ['pending', 'accepted']);
        if (!$result['checks']['quote_status_valid']) {
            $result['errors'][] = 'FX quote status is invalid for execution';
        }

        $result['checks']['rate_positive'] = $quote->rate > 0;
        if (!$result['checks']['rate_positive']) {
            $result['errors'][] = 'FX rate must be positive';
        }

        if ($quote->source_amount) {
            $expectedDestination = $quote->source_amount * $quote->rate;
            $tolerance = $expectedDestination * 0.001;
            
            $result['checks']['amount_calculation_valid'] = 
                abs($quote->destination_amount - $expectedDestination) <= $tolerance;
            if (!$result['checks']['amount_calculation_valid']) {
                $result['errors'][] = 'FX amount calculation mismatch';
            }
        }

        $result['valid'] = empty($result['errors']);

        $this->auditService->log(
            'reconciliation_fx_validated',
            'fx_quote',
            $quote->id,
            null,
            $result
        );

        return $result;
    }

    /**
     * Match amounts across all transaction legs
     */
    public function matchAmountsAcrossLegs(TransactionIntent $transaction): array
    {
        $result = [
            'matched' => true,
            'legs' => [],
            'discrepancies' => [],
        ];

        $debitAmount = $transaction->amount;
        $result['legs']['debit'] = [
            'amount' => $debitAmount,
            'currency' => $transaction->currency,
        ];

        $instruction = $transaction->paymentInstruction;
        if ($instruction) {
            $result['legs']['instruction'] = [
                'amount' => $instruction->amount,
                'currency' => $instruction->currency,
            ];

            if (abs($debitAmount - $instruction->amount) > 0.01) {
                $result['discrepancies'][] = [
                    'type' => 'amount_mismatch',
                    'legs' => ['debit', 'instruction'],
                    'difference' => abs($debitAmount - $instruction->amount),
                ];
            }
        }

        $metadata = $transaction->metadata ?? [];
        if (isset($metadata['fee_amount'])) {
            $result['legs']['fee'] = [
                'amount' => $metadata['fee_amount'],
                'currency' => $transaction->currency,
            ];

            $netAmount = $debitAmount - $metadata['fee_amount'];
            if ($instruction && abs($instruction->amount - $netAmount) > 0.01) {
                $result['discrepancies'][] = [
                    'type' => 'net_amount_mismatch',
                    'expected_net' => $netAmount,
                    'actual_instruction' => $instruction->amount,
                ];
            }
        }

        $result['matched'] = empty($result['discrepancies']);

        $this->auditService->log(
            'reconciliation_amounts_matched',
            'transaction_intent',
            $transaction->id,
            null,
            $result
        );

        return $result;
    }

    /**
     * Finalize transaction after all validations pass
     */
    public function finalizeTransaction(TransactionIntent $transaction): array
    {
        $debitResult = $this->validateDebit($transaction);
        $creditResult = $this->validateCredit($transaction);
        $amountResult = $this->matchAmountsAcrossLegs($transaction);

        $allValid = $debitResult['valid'] && $creditResult['valid'] && $amountResult['matched'];

        if (!$allValid) {
            $errors = array_merge(
                $debitResult['errors'] ?? [],
                $creditResult['errors'] ?? [],
                array_map(fn ($d) => $d['type'], $amountResult['discrepancies'] ?? [])
            );

            $this->auditService->log(
                'reconciliation_finalization_failed',
                'transaction_intent',
                $transaction->id,
                null,
                [
                    'errors' => $errors,
                    'debit_result' => $debitResult,
                    'credit_result' => $creditResult,
                    'amount_result' => $amountResult,
                ]
            );

            return [
                'finalized' => false,
                'errors' => $errors,
            ];
        }

        $transaction->update([
            'reconciliation_status' => 'reconciled',
            'reconciled_at' => now(),
        ]);

        $this->auditService->log(
            'reconciliation_finalized',
            'transaction_intent',
            $transaction->id,
            null,
            [
                'reconciled_at' => now()->toIso8601String(),
            ]
        );

        return [
            'finalized' => true,
            'reconciled_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get reconciliation report for a date range
     */
    public function getReconciliationReport(string $startDate, string $endDate): array
    {
        $transactions = TransactionIntent::whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'total_transactions' => $transactions->count(),
            'reconciled' => $transactions->where('reconciliation_status', 'reconciled')->count(),
            'pending' => $transactions->whereNull('reconciliation_status')->count(),
            'failed' => $transactions->where('reconciliation_status', 'failed')->count(),
            'total_volume' => $transactions->sum('amount'),
            'by_currency' => $transactions->groupBy('currency')
                ->map(fn ($group) => [
                    'count' => $group->count(),
                    'volume' => $group->sum('amount'),
                ])
                ->toArray(),
        ];
    }
}

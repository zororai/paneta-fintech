<?php

namespace App\Services;

use App\Models\CrossBorderTransactionIntent;
use App\Models\FxQuote;
use App\Models\LinkedAccount;
use App\Models\User;
use App\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

class CrossBorderOrchestrationEngine
{
    public function __construct(
        protected FXDiscoveryEngine $fxDiscovery,
        protected CompositeInstructionBuilder $instructionBuilder,
        protected FeeEngine $feeEngine,
        protected ComplianceEngine $complianceEngine,
        protected AuditService $auditService,
        protected ReconciliationEngine $reconciliationEngine
    ) {}

    public function createCrossBorderIntent(
        User $user,
        LinkedAccount $sourceAccount,
        string $destinationIdentifier,
        float $sourceAmount,
        string $destinationCurrency,
        ?string $destinationCountry = null,
        ?string $idempotencyKey = null
    ): CrossBorderIntentResult {
        if ($idempotencyKey) {
            $existing = CrossBorderTransactionIntent::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return new CrossBorderIntentResult(
                    success: true,
                    intent: $existing,
                    idempotentReplay: true
                );
            }
        }

        if ($sourceAccount->user_id !== $user->id) {
            return new CrossBorderIntentResult(
                success: false,
                error: 'Account does not belong to user'
            );
        }

        $complianceResult = $this->complianceEngine->checkTransaction($user, $sourceAccount, $sourceAmount);
        if (!$complianceResult->passed) {
            return new CrossBorderIntentResult(
                success: false,
                error: $complianceResult->failureReason,
                complianceChecks: $complianceResult->checks
            );
        }

        $fxQuote = $this->fxDiscovery->getBestRate(
            $sourceAccount->currency,
            $destinationCurrency,
            $sourceAmount
        );

        if (!$fxQuote) {
            return new CrossBorderIntentResult(
                success: false,
                error: 'No FX rate available for this currency pair'
            );
        }

        $feeAmount = $this->feeEngine->calculateFee($sourceAmount, 'cross_border');
        $destinationAmount = round($sourceAmount * $fxQuote->rate, 2);

        $intent = CrossBorderTransactionIntent::create([
            'user_id' => $user->id,
            'source_account_id' => $sourceAccount->id,
            'destination_identifier' => $destinationIdentifier,
            'destination_country' => $destinationCountry,
            'source_currency' => $sourceAccount->currency,
            'destination_currency' => $destinationCurrency,
            'source_amount' => $sourceAmount,
            'destination_amount' => $destinationAmount,
            'fx_rate' => $fxQuote->rate,
            'fx_provider_id' => $fxQuote->fx_provider_id,
            'fx_quote_id' => $fxQuote->id,
            'fee_amount' => $feeAmount,
            'fee_currency' => $sourceAccount->currency,
            'status' => 'pending',
            'reference' => CrossBorderTransactionIntent::generateReference(),
            'idempotency_key' => $idempotencyKey,
            'leg_statuses' => [],
        ]);

        $this->auditService->log(
            'cross_border_intent_created',
            'CrossBorderTransactionIntent',
            $intent->id,
            $user,
            [
                'source_amount' => $sourceAmount,
                'destination_amount' => $destinationAmount,
                'fx_rate' => $fxQuote->rate,
                'fee' => $feeAmount,
            ]
        );

        return new CrossBorderIntentResult(
            success: true,
            intent: $intent,
            fxQuote: $fxQuote,
            feeAmount: $feeAmount
        );
    }

    public function executeCrossBorderTransaction(CrossBorderTransactionIntent $intent): CrossBorderExecutionResult
    {
        if ($intent->status !== 'pending') {
            return new CrossBorderExecutionResult(
                success: false,
                error: 'Transaction is not in pending state'
            );
        }

        $fxQuote = $intent->fxQuote;
        if (!$fxQuote || !$this->fxDiscovery->isQuoteValid($fxQuote)) {
            return new CrossBorderExecutionResult(
                success: false,
                error: 'FX quote has expired'
            );
        }

        try {
            return DB::transaction(function () use ($intent, $fxQuote) {
                $intent->transitionTo('fx_locked');
                $intent->updateLegStatus('fx_quote', 'completed');

                $sourceAccount = LinkedAccount::lockForUpdate()->find($intent->source_account_id);
                $totalDebit = $intent->getTotalDebitAmount();

                if ($sourceAccount->mock_balance < $totalDebit) {
                    $intent->markFailed('Insufficient balance');
                    return new CrossBorderExecutionResult(
                        success: false,
                        error: 'Insufficient balance'
                    );
                }

                $sourceAccount->decrement('mock_balance', $totalDebit);
                $intent->transitionTo('source_debited');
                $intent->updateLegStatus('source_debit', 'completed');

                $fxSuccess = rand(1, 100) <= 98;
                if (!$fxSuccess) {
                    $sourceAccount->increment('mock_balance', $totalDebit);
                    $intent->markFailed('FX conversion failed');
                    return new CrossBorderExecutionResult(
                        success: false,
                        error: 'FX conversion failed'
                    );
                }

                $intent->transitionTo('fx_executed');
                $intent->updateLegStatus('fx_conversion', 'completed');

                $creditSuccess = rand(1, 100) <= 95;
                if (!$creditSuccess) {
                    $sourceAccount->increment('mock_balance', $totalDebit);
                    $intent->markFailed('Destination credit failed');
                    return new CrossBorderExecutionResult(
                        success: false,
                        error: 'Destination credit failed'
                    );
                }

                $intent->transitionTo('destination_credited');
                $intent->updateLegStatus('destination_credit', 'completed');

                $intent->transitionTo('completed');

                $this->feeEngine->recordFee(
                    $intent->user,
                    'cross_border_transaction',
                    $intent->id,
                    $intent->fee_amount,
                    $intent->fee_currency,
                    'cross_border'
                );

                $this->auditService->log(
                    $intent->user_id,
                    'cross_border_completed',
                    'CrossBorderTransactionIntent',
                    $intent->id,
                    [
                        'source_amount' => $intent->source_amount,
                        'destination_amount' => $intent->destination_amount,
                        'fx_rate' => $intent->fx_rate,
                    ]
                );

                return new CrossBorderExecutionResult(
                    success: true,
                    intent: $intent->fresh(),
                    reference: $intent->reference
                );
            });
        } catch (\Exception $e) {
            $intent->markFailed($e->getMessage());

            $this->auditService->log(
                $intent->user_id,
                'cross_border_failed',
                'CrossBorderTransactionIntent',
                $intent->id,
                ['error' => $e->getMessage()]
            );

            return new CrossBorderExecutionResult(
                success: false,
                error: $e->getMessage()
            );
        }
    }

    public function getTransactionStatus(CrossBorderTransactionIntent $intent): array
    {
        return [
            'id' => $intent->id,
            'reference' => $intent->reference,
            'status' => $intent->status,
            'leg_statuses' => $intent->leg_statuses,
            'source_amount' => $intent->source_amount,
            'source_currency' => $intent->source_currency,
            'destination_amount' => $intent->destination_amount,
            'destination_currency' => $intent->destination_currency,
            'fx_rate' => $intent->fx_rate,
            'fee_amount' => $intent->fee_amount,
            'created_at' => $intent->created_at,
            'failure_reason' => $intent->failure_reason,
        ];
    }
}

class CrossBorderIntentResult
{
    public function __construct(
        public bool $success,
        public ?CrossBorderTransactionIntent $intent = null,
        public ?string $error = null,
        public ?FxQuote $fxQuote = null,
        public float $feeAmount = 0,
        public array $complianceChecks = [],
        public bool $idempotentReplay = false
    ) {}
}

class CrossBorderExecutionResult
{
    public function __construct(
        public bool $success,
        public ?CrossBorderTransactionIntent $intent = null,
        public ?string $error = null,
        public ?string $reference = null
    ) {}
}

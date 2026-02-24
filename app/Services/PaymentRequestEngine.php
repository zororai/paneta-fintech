<?php

namespace App\Services;

use App\Models\PaymentRequest;
use App\Models\LinkedAccount;
use App\Models\User;
use App\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Str;

class PaymentRequestEngine
{
    public function __construct(
        protected AuditService $auditService,
        protected FeeEngine $feeEngine
    ) {}

    public function createPaymentRequest(
        User $user,
        float $amount,
        string $currency,
        ?LinkedAccount $linkedAccount = null,
        ?string $description = null,
        bool $allowPartial = false,
        ?int $expiresInMinutes = null,
        ?string $idempotencyKey = null
    ): PaymentRequest {
        if ($idempotencyKey) {
            $existing = PaymentRequest::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $paymentRequest = PaymentRequest::create([
            'user_id' => $user->id,
            'linked_account_id' => $linkedAccount?->id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'reference' => PaymentRequest::generateReference(),
            'description' => $description,
            'allow_partial' => $allowPartial,
            'expires_at' => $expiresInMinutes ? now()->addMinutes($expiresInMinutes) : null,
            'idempotency_key' => $idempotencyKey,
        ]);

        $paymentRequest->qr_code_data = $paymentRequest->generateQrCodeData();
        $paymentRequest->save();

        $this->auditService->log(
            'payment_request_created',
            'PaymentRequest',
            $paymentRequest->id,
            $user,
            [
                'amount' => $amount,
                'currency' => $currency,
                'allow_partial' => $allowPartial,
            ]
        );

        return $paymentRequest;
    }

    public function fulfillPaymentRequest(
        PaymentRequest $paymentRequest,
        User $payer,
        LinkedAccount $payerAccount,
        ?float $amount = null
    ): PaymentRequestFulfillmentResult {
        if ($paymentRequest->isExpired()) {
            $paymentRequest->transitionTo('expired');
            return new PaymentRequestFulfillmentResult(
                success: false,
                error: 'Payment request has expired'
            );
        }

        if ($paymentRequest->status === 'completed') {
            return new PaymentRequestFulfillmentResult(
                success: false,
                error: 'Payment request already fulfilled'
            );
        }

        $paymentAmount = $amount ?? $paymentRequest->getRemainingAmount();

        if (!$paymentRequest->allow_partial && $paymentAmount < $paymentRequest->amount) {
            return new PaymentRequestFulfillmentResult(
                success: false,
                error: 'Partial payments not allowed for this request'
            );
        }

        if ($payerAccount->mock_balance < $paymentAmount) {
            return new PaymentRequestFulfillmentResult(
                success: false,
                error: 'Insufficient balance'
            );
        }

        $payerAccount->decrement('mock_balance', $paymentAmount);

        if ($paymentRequest->linkedAccount) {
            $paymentRequest->linkedAccount->increment('mock_balance', $paymentAmount);
        }

        $paymentRequest->recordPayment($paymentAmount);

        $this->auditService->log(
            'payment_request_fulfilled',
            'PaymentRequest',
            $paymentRequest->id,
            $payer,
            [
                'amount_paid' => $paymentAmount,
                'total_received' => $paymentRequest->amount_received,
                'status' => $paymentRequest->status,
            ]
        );

        return new PaymentRequestFulfillmentResult(
            success: true,
            amountPaid: $paymentAmount,
            remainingAmount: $paymentRequest->getRemainingAmount(),
            isComplete: $paymentRequest->status === 'completed'
        );
    }

    public function cancelPaymentRequest(PaymentRequest $paymentRequest, User $user): bool
    {
        if (!in_array($paymentRequest->status, ['pending', 'partially_fulfilled'])) {
            return false;
        }

        $paymentRequest->transitionTo('cancelled');

        $this->auditService->log(
            'payment_request_cancelled',
            'PaymentRequest',
            $paymentRequest->id,
            $user,
            ['amount_received_before_cancel' => $paymentRequest->amount_received]
        );

        return true;
    }

    public function expireOldRequests(): int
    {
        $expired = PaymentRequest::whereIn('status', ['pending', 'partially_fulfilled'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $request) {
            $request->update(['status' => 'expired']);
        }

        return $expired->count();
    }

    public function getActiveRequests(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return PaymentRequest::forUser($user->id)
            ->active()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findByReference(string $reference): ?PaymentRequest
    {
        return PaymentRequest::where('reference', $reference)->first();
    }
}

class PaymentRequestFulfillmentResult
{
    public function __construct(
        public bool $success,
        public ?string $error = null,
        public float $amountPaid = 0,
        public float $remainingAmount = 0,
        public bool $isComplete = false
    ) {}
}

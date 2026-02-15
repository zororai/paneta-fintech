<?php

namespace App\Jobs;

use App\Models\LinkedAccount;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Services\PaymentRequestEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentRequestFulfillment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [5, 15, 45, 120, 300];

    protected int $paymentRequestId;
    protected int $payerId;
    protected int $payerAccountId;
    protected ?float $amount;

    public function __construct(int $paymentRequestId, int $payerId, int $payerAccountId, ?float $amount = null)
    {
        $this->paymentRequestId = $paymentRequestId;
        $this->payerId = $payerId;
        $this->payerAccountId = $payerAccountId;
        $this->amount = $amount;
        $this->onQueue('payments');
    }

    public function handle(PaymentRequestEngine $engine): void
    {
        $paymentRequest = PaymentRequest::findOrFail($this->paymentRequestId);
        $payer = User::findOrFail($this->payerId);
        $payerAccount = LinkedAccount::findOrFail($this->payerAccountId);

        Log::info("Processing payment request fulfillment", [
            'payment_request_id' => $this->paymentRequestId,
            'payer_id' => $this->payerId,
            'attempt' => $this->attempts(),
        ]);

        $engine->fulfillPaymentRequest($paymentRequest, $payer, $payerAccount, $this->amount);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Payment request fulfillment failed", [
            'payment_request_id' => $this->paymentRequestId,
            'error' => $exception->getMessage(),
        ]);
    }
}

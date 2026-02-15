<?php

namespace App\Events;

use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentRequestFulfilled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PaymentRequest $paymentRequest;
    public User $payer;
    public float $amount;

    public function __construct(PaymentRequest $paymentRequest, User $payer, float $amount)
    {
        $this->paymentRequest = $paymentRequest;
        $this->payer = $payer;
        $this->amount = $amount;
    }
}

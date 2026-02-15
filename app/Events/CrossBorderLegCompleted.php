<?php

namespace App\Events;

use App\Models\CrossBorderTransactionIntent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrossBorderLegCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public CrossBorderTransactionIntent $transaction;
    public string $legName;
    public string $status;

    public function __construct(CrossBorderTransactionIntent $transaction, string $legName, string $status)
    {
        $this->transaction = $transaction;
        $this->legName = $legName;
        $this->status = $status;
    }
}

<?php

namespace App\Listeners;

use App\Events\TransactionExecuted;
use App\Services\TreasuryLedgerService;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordTransactionFee implements ShouldQueue
{
    public string $queue = 'fees';

    protected TreasuryLedgerService $treasuryService;

    public function __construct(TreasuryLedgerService $treasuryService)
    {
        $this->treasuryService = $treasuryService;
    }

    public function handle(TransactionExecuted $event): void
    {
        $feeAmount = $event->transaction->amount * 0.0099;

        $this->treasuryService->recordFeeCollection(
            $feeAmount,
            $event->transaction->currency,
            'TransactionIntent',
            $event->transaction->id,
            $event->user,
            "Platform fee for transaction {$event->transaction->reference}"
        );
    }
}

<?php

namespace App\Jobs;

use App\Models\CrossBorderTransactionIntent;
use App\Services\CrossBorderOrchestrationEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteCrossBorderTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [5, 15, 45, 120, 300];

    protected int $transactionId;

    public function __construct(int $transactionId)
    {
        $this->transactionId = $transactionId;
        $this->onQueue('cross-border');
    }

    public function handle(CrossBorderOrchestrationEngine $engine): void
    {
        $transaction = CrossBorderTransactionIntent::findOrFail($this->transactionId);

        Log::info("Executing cross-border transaction", [
            'transaction_id' => $transaction->id,
            'attempt' => $this->attempts(),
        ]);

        $engine->executeCrossBorderTransaction($transaction);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Cross-border transaction failed permanently", [
            'transaction_id' => $this->transactionId,
            'error' => $exception->getMessage(),
        ]);

        $transaction = CrossBorderTransactionIntent::find($this->transactionId);
        if ($transaction) {
            $transaction->update([
                'status' => 'failed',
                'failure_reason' => $exception->getMessage(),
            ]);
        }
    }
}

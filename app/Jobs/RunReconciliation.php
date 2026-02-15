<?php

namespace App\Jobs;

use App\Services\ReconciliationEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunReconciliation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue('reconciliation');
    }

    public function handle(ReconciliationEngine $engine): void
    {
        Log::info("Starting reconciliation run");

        $timeouts = $engine->detectTimeouts();

        foreach ($timeouts as $timeout) {
            try {
                $engine->handleTimeout($timeout);
            } catch (\Throwable $e) {
                Log::error("Failed to handle timeout", [
                    'transaction_id' => $timeout->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $report = $engine->getReconciliationReport();

        Log::info("Reconciliation completed", $report);
    }
}

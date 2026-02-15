<?php

namespace App\Jobs;

use App\Models\FxQuote;
use App\Events\FxQuoteExpired;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireQuotesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $expiredCount = 0;
        
        // Find all quotes that have expired but not yet marked as expired
        $expiredQuotes = FxQuote::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->limit(1000) // Process in batches
            ->get();

        foreach ($expiredQuotes as $quote) {
            $quote->update(['status' => 'expired']);
            
            // Dispatch event for any listeners
            event(new FxQuoteExpired($quote));
            
            $expiredCount++;
        }

        if ($expiredCount > 0) {
            Log::info('Expired FX quotes processed', [
                'count' => $expiredCount,
                'processed_at' => now()->toIso8601String(),
            ]);
        }

        // Also expire any locked quotes that weren't executed in time
        $expiredLockedQuotes = FxQuote::where('status', 'locked')
            ->where('lock_expires_at', '<', now())
            ->limit(500)
            ->get();

        foreach ($expiredLockedQuotes as $quote) {
            $quote->update(['status' => 'expired']);
            event(new FxQuoteExpired($quote));
        }

        if ($expiredLockedQuotes->count() > 0) {
            Log::info('Expired locked FX quotes processed', [
                'count' => $expiredLockedQuotes->count(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ExpireQuotesJob failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}

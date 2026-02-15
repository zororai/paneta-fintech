<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\WealthAnalyticsEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecalculatePortfolio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 30, 120];

    protected int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->onQueue('analytics');
    }

    public function handle(WealthAnalyticsEngine $engine): void
    {
        $user = User::findOrFail($this->userId);

        Log::info("Recalculating portfolio", ['user_id' => $this->userId]);

        $engine->calculatePortfolio($user);

        Log::info("Portfolio recalculated", ['user_id' => $this->userId]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Portfolio recalculation failed", [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\KeyRotation;
use App\Models\User;
use App\Services\KeyManagementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RotateKeys implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('security');
    }

    public function handle(KeyManagementService $keyService): void
    {
        Log::info("Starting scheduled key rotation check");

        $keysNeedingRotation = $keyService->getKeysNeedingRotation();

        if (empty($keysNeedingRotation)) {
            Log::info("No keys need rotation");
            return;
        }

        $systemUser = User::where('role', 'admin')->first();

        if (!$systemUser) {
            Log::error("No admin user found for key rotation");
            return;
        }

        foreach ($keysNeedingRotation as $keyInfo) {
            try {
                $keyService->rotateKey(
                    $keyInfo['key_type'],
                    $systemUser,
                    $keyInfo['reason']
                );

                Log::info("Key rotated", [
                    'key_type' => $keyInfo['key_type'],
                    'reason' => $keyInfo['reason'],
                ]);
            } catch (\Throwable $e) {
                Log::error("Key rotation failed", [
                    'key_type' => $keyInfo['key_type'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

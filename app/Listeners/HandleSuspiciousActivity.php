<?php

namespace App\Listeners;

use App\Events\SuspiciousActivityDetected;
use App\Services\NotificationService;
use App\Services\SecurityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleSuspiciousActivity implements ShouldQueue
{
    public string $queue = 'security';

    protected NotificationService $notificationService;
    protected SecurityService $securityService;

    public function __construct(NotificationService $notificationService, SecurityService $securityService)
    {
        $this->notificationService = $notificationService;
        $this->securityService = $securityService;
    }

    public function handle(SuspiciousActivityDetected $event): void
    {
        Log::warning("Suspicious activity detected", [
            'user_id' => $event->user?->id,
            'reason' => $event->reason,
            'metadata' => $event->metadata,
        ]);

        if ($event->user) {
            $this->notificationService->sendSuspiciousActivity($event->user, [
                'reason' => $event->reason,
                'detected_at' => now()->toIso8601String(),
            ]);
        }
    }
}

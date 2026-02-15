<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\RiskTierAssignmentService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReverificationSchedulerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('security');
    }

    public function handle(RiskTierAssignmentService $riskService, NotificationService $notifications): void
    {
        Log::info('Starting reverification scheduler');

        // Find users needing reverification
        $usersNeedingReview = User::whereNotNull('next_reverification_at')
            ->where('next_reverification_at', '<=', now())
            ->limit(100) // Process in batches
            ->get();

        $processed = 0;
        $tierChanges = 0;

        foreach ($usersNeedingReview as $user) {
            try {
                $previousTier = $user->risk_tier;
                $assessment = $riskService->assessRisk($user);
                $riskService->assignTier($user, $assessment['risk_tier'], 'Scheduled reverification');

                if ($previousTier !== $assessment['risk_tier']) {
                    $tierChanges++;
                    
                    // Notify user of tier change
                    $notifications->send(
                        $user,
                        'risk_tier_changed',
                        'Account Status Updated',
                        "Your account risk tier has been updated to {$assessment['risk_tier']}.",
                        null,
                        ['new_tier' => $assessment['risk_tier'], 'previous_tier' => $previousTier]
                    );
                }

                $processed++;

            } catch (\Exception $e) {
                Log::error('Reverification failed for user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Also check for users approaching reverification (send reminder)
        $usersApproachingReview = User::whereNotNull('next_reverification_at')
            ->whereBetween('next_reverification_at', [now(), now()->addDays(7)])
            ->get();

        foreach ($usersApproachingReview as $user) {
            // Check if we haven't already notified them recently
            $alreadyNotified = $user->notifications()
                ->where('type', 'reverification_reminder')
                ->where('created_at', '>=', now()->subDays(7))
                ->exists();

            if (!$alreadyNotified) {
                $notifications->send(
                    $user,
                    'reverification_reminder',
                    'Account Review Coming Up',
                    'Your account will undergo a scheduled review soon. Please ensure your information is up to date.',
                    route('paneta.settings')
                );
            }
        }

        Log::info('Reverification scheduler completed', [
            'processed' => $processed,
            'tier_changes' => $tierChanges,
            'approaching_review' => $usersApproachingReview->count(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ReverificationSchedulerJob failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}

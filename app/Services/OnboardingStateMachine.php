<?php

namespace App\Services;

use App\Models\User;
use App\Models\OnboardingProgress;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class OnboardingStateMachine
{
    const STAGE_REGISTERED = 'registered';
    const STAGE_EMAIL_VERIFIED = 'email_verified';
    const STAGE_CONTACT_VERIFIED = 'contact_verified';
    const STAGE_BASIC_ACCESS = 'basic_access';
    const STAGE_KYC_SUBMITTED = 'kyc_submitted';
    const STAGE_KYC_VERIFIED = 'kyc_verified';
    const STAGE_RISK_TIERED = 'risk_tiered';
    const STAGE_FIRST_TRANSACTION = 'first_transaction';
    const STAGE_FULLY_ONBOARDED = 'fully_onboarded';
    const STAGE_SUSPENDED = 'suspended';
    const STAGE_CLOSED = 'closed';

    const TRANSITIONS = [
        self::STAGE_REGISTERED => [self::STAGE_EMAIL_VERIFIED, self::STAGE_SUSPENDED, self::STAGE_CLOSED],
        self::STAGE_EMAIL_VERIFIED => [self::STAGE_CONTACT_VERIFIED, self::STAGE_BASIC_ACCESS, self::STAGE_SUSPENDED],
        self::STAGE_CONTACT_VERIFIED => [self::STAGE_BASIC_ACCESS, self::STAGE_SUSPENDED],
        self::STAGE_BASIC_ACCESS => [self::STAGE_KYC_SUBMITTED, self::STAGE_SUSPENDED],
        self::STAGE_KYC_SUBMITTED => [self::STAGE_KYC_VERIFIED, self::STAGE_BASIC_ACCESS, self::STAGE_SUSPENDED],
        self::STAGE_KYC_VERIFIED => [self::STAGE_RISK_TIERED, self::STAGE_SUSPENDED],
        self::STAGE_RISK_TIERED => [self::STAGE_FIRST_TRANSACTION, self::STAGE_SUSPENDED],
        self::STAGE_FIRST_TRANSACTION => [self::STAGE_FULLY_ONBOARDED, self::STAGE_SUSPENDED],
        self::STAGE_FULLY_ONBOARDED => [self::STAGE_SUSPENDED, self::STAGE_CLOSED],
        self::STAGE_SUSPENDED => [self::STAGE_BASIC_ACCESS, self::STAGE_KYC_VERIFIED, self::STAGE_CLOSED],
        self::STAGE_CLOSED => [],
    ];

    const STEPS = [
        'registration' => ['required' => true, 'stage' => self::STAGE_REGISTERED],
        'email_verification' => ['required' => true, 'stage' => self::STAGE_EMAIL_VERIFIED],
        'phone_verification' => ['required' => false, 'stage' => self::STAGE_CONTACT_VERIFIED],
        'profile_completion' => ['required' => true, 'stage' => self::STAGE_BASIC_ACCESS],
        'kyc_document_upload' => ['required' => true, 'stage' => self::STAGE_KYC_SUBMITTED],
        'kyc_verification' => ['required' => true, 'stage' => self::STAGE_KYC_VERIFIED],
        'risk_assessment' => ['required' => true, 'stage' => self::STAGE_RISK_TIERED],
        'first_account_link' => ['required' => false, 'stage' => self::STAGE_FIRST_TRANSACTION],
        'first_transaction' => ['required' => false, 'stage' => self::STAGE_FIRST_TRANSACTION],
    ];

    protected AuditService $audit;
    protected NotificationService $notifications;

    public function __construct(AuditService $audit, NotificationService $notifications)
    {
        $this->audit = $audit;
        $this->notifications = $notifications;
    }

    public function canTransition(User $user, string $targetStage): bool
    {
        $currentStage = $user->onboarding_stage ?? self::STAGE_REGISTERED;
        $allowedTransitions = self::TRANSITIONS[$currentStage] ?? [];
        
        return in_array($targetStage, $allowedTransitions);
    }

    public function transition(User $user, string $targetStage, array $data = []): bool
    {
        if (!$this->canTransition($user, $targetStage)) {
            Log::warning('Invalid onboarding transition attempted', [
                'user_id' => $user->id,
                'current_stage' => $user->onboarding_stage,
                'target_stage' => $targetStage,
            ]);
            return false;
        }

        $previousStage = $user->onboarding_stage;

        $user->update([
            'onboarding_stage' => $targetStage,
            'onboarding_completed_at' => $targetStage === self::STAGE_FULLY_ONBOARDED ? now() : null,
        ]);

        $this->audit->log(
            $user->id,
            'onboarding_stage_changed',
            'user',
            $user->id,
            [
                'previous_stage' => $previousStage,
                'new_stage' => $targetStage,
                'data' => $data,
            ]
        );

        // Send appropriate notifications
        $this->sendStageNotification($user, $targetStage);

        Log::info('Onboarding stage transitioned', [
            'user_id' => $user->id,
            'from' => $previousStage,
            'to' => $targetStage,
        ]);

        return true;
    }

    public function completeStep(User $user, string $step, array $data = []): bool
    {
        $progress = OnboardingProgress::firstOrCreate(
            ['user_id' => $user->id, 'step' => $step],
            ['status' => OnboardingProgress::STATUS_PENDING]
        );

        $progress->complete($data);

        // Check if this step triggers a stage transition
        $stepConfig = self::STEPS[$step] ?? null;
        if ($stepConfig && isset($stepConfig['stage'])) {
            $this->tryAutoTransition($user);
        }

        return true;
    }

    public function getProgress(User $user): array
    {
        $progress = OnboardingProgress::where('user_id', $user->id)->get()->keyBy('step');

        $steps = [];
        foreach (self::STEPS as $step => $config) {
            $stepProgress = $progress[$step] ?? null;
            $steps[$step] = [
                'required' => $config['required'],
                'status' => $stepProgress?->status ?? 'pending',
                'completed_at' => $stepProgress?->completed_at?->toIso8601String(),
            ];
        }

        $completedRequired = collect($steps)->filter(fn($s) => $s['required'] && $s['status'] === 'completed')->count();
        $totalRequired = collect(self::STEPS)->filter(fn($s) => $s['required'])->count();

        return [
            'user_id' => $user->id,
            'current_stage' => $user->onboarding_stage ?? self::STAGE_REGISTERED,
            'is_complete' => $user->onboarding_stage === self::STAGE_FULLY_ONBOARDED,
            'progress_percentage' => $totalRequired > 0 ? round(($completedRequired / $totalRequired) * 100) : 0,
            'steps' => $steps,
            'next_step' => $this->getNextRequiredStep($steps),
        ];
    }

    public function getNextRequiredStep(array $steps): ?string
    {
        foreach (self::STEPS as $step => $config) {
            if ($config['required'] && ($steps[$step]['status'] ?? 'pending') !== 'completed') {
                return $step;
            }
        }
        return null;
    }

    protected function tryAutoTransition(User $user): void
    {
        $progress = $this->getProgress($user);
        $currentStage = $user->onboarding_stage ?? self::STAGE_REGISTERED;

        // Define auto-transition rules
        $autoTransitions = [
            self::STAGE_REGISTERED => [
                'condition' => fn() => $user->email_verified_at !== null,
                'target' => self::STAGE_EMAIL_VERIFIED,
            ],
            self::STAGE_EMAIL_VERIFIED => [
                'condition' => fn() => ($progress['steps']['profile_completion']['status'] ?? '') === 'completed',
                'target' => self::STAGE_BASIC_ACCESS,
            ],
            self::STAGE_BASIC_ACCESS => [
                'condition' => fn() => ($progress['steps']['kyc_document_upload']['status'] ?? '') === 'completed',
                'target' => self::STAGE_KYC_SUBMITTED,
            ],
            self::STAGE_KYC_SUBMITTED => [
                'condition' => fn() => $user->kyc_status === 'verified',
                'target' => self::STAGE_KYC_VERIFIED,
            ],
            self::STAGE_KYC_VERIFIED => [
                'condition' => fn() => ($progress['steps']['risk_assessment']['status'] ?? '') === 'completed',
                'target' => self::STAGE_RISK_TIERED,
            ],
            self::STAGE_RISK_TIERED => [
                'condition' => fn() => $user->transactionIntents()->where('status', 'executed')->exists(),
                'target' => self::STAGE_FIRST_TRANSACTION,
            ],
            self::STAGE_FIRST_TRANSACTION => [
                'condition' => fn() => $progress['progress_percentage'] >= 100,
                'target' => self::STAGE_FULLY_ONBOARDED,
            ],
        ];

        if (isset($autoTransitions[$currentStage])) {
            $rule = $autoTransitions[$currentStage];
            if ($rule['condition']()) {
                $this->transition($user, $rule['target']);
            }
        }
    }

    protected function sendStageNotification(User $user, string $stage): void
    {
        $messages = [
            self::STAGE_EMAIL_VERIFIED => ['title' => 'Email Verified', 'message' => 'Your email has been verified. Complete your profile to continue.'],
            self::STAGE_KYC_VERIFIED => ['title' => 'Identity Verified', 'message' => 'Your identity has been verified. You can now make transactions.'],
            self::STAGE_FULLY_ONBOARDED => ['title' => 'Welcome to PANÉTA!', 'message' => 'Your account is fully set up. Enjoy using PANÉTA!'],
            self::STAGE_SUSPENDED => ['title' => 'Account Suspended', 'message' => 'Your account has been suspended. Please contact support.'],
        ];

        if (isset($messages[$stage])) {
            $this->notifications->send(
                $user,
                'onboarding_' . $stage,
                $messages[$stage]['title'],
                $messages[$stage]['message']
            );
        }
    }

    public function suspend(User $user, string $reason): bool
    {
        return $this->transition($user, self::STAGE_SUSPENDED, ['reason' => $reason]);
    }

    public function reactivate(User $user, string $targetStage = self::STAGE_BASIC_ACCESS): bool
    {
        if ($user->onboarding_stage !== self::STAGE_SUSPENDED) {
            return false;
        }
        return $this->transition($user, $targetStage);
    }
}

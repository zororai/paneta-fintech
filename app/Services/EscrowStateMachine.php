<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * EscrowStateMachine
 * 
 * Manages P2P FX escrow state transitions:
 * - Logical escrow state model
 * - Time-bound escrow activation window
 * - Dual-party confirmation barrier
 * - Automatic timeout handling
 * 
 * States: OFFER_OPEN → PENDING_VALIDATION → ESCROW_ACTIVE → EXECUTION_DISPATCHED → COMPLETED/CANCELLED
 */
class EscrowStateMachine
{
    public const STATE_OFFER_OPEN = 'offer_open';
    public const STATE_PENDING_VALIDATION = 'pending_validation';
    public const STATE_ESCROW_ACTIVE = 'escrow_active';
    public const STATE_EXECUTION_DISPATCHED = 'execution_dispatched';
    public const STATE_COMPLETED = 'completed';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_EXPIRED = 'expired';

    private const ESCROW_TIMEOUT_MINUTES = 30;
    private const EXECUTION_WINDOW_MINUTES = 5;

    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Create a new escrow offer
     */
    public function createEscrowOffer(
        User $initiator,
        array $offerDetails
    ): array {
        $escrowId = $this->generateEscrowId();

        $escrow = [
            'id' => $escrowId,
            'state' => self::STATE_OFFER_OPEN,
            'initiator_id' => $initiator->id,
            'counterparty_id' => null,
            'offer_details' => $offerDetails,
            'initiator_confirmed' => false,
            'counterparty_confirmed' => false,
            'created_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes(self::ESCROW_TIMEOUT_MINUTES)->toIso8601String(),
            'state_history' => [
                [
                    'state' => self::STATE_OFFER_OPEN,
                    'timestamp' => now()->toIso8601String(),
                    'actor_id' => $initiator->id,
                ],
            ],
        ];

        $this->storeEscrow($escrow);

        $this->auditService->log(
            'escrow_offer_created',
            'escrow',
            $escrowId,
            $initiator,
            [
                'offer_details' => $offerDetails,
                'expires_at' => $escrow['expires_at'],
            ]
        );

        return $escrow;
    }

    /**
     * Accept escrow offer (counterparty joins)
     */
    public function acceptOffer(string $escrowId, User $counterparty): array
    {
        $escrow = $this->getEscrow($escrowId);

        $this->validateStateTransition($escrow, self::STATE_PENDING_VALIDATION);

        if ($escrow['initiator_id'] === $counterparty->id) {
            throw new \RuntimeException('Initiator cannot be counterparty');
        }

        $escrow['counterparty_id'] = $counterparty->id;
        $escrow['state'] = self::STATE_PENDING_VALIDATION;
        $escrow['state_history'][] = [
            'state' => self::STATE_PENDING_VALIDATION,
            'timestamp' => now()->toIso8601String(),
            'actor_id' => $counterparty->id,
        ];

        $this->storeEscrow($escrow);

        $this->auditService->log(
            'escrow_offer_accepted',
            'escrow',
            $escrowId,
            $counterparty,
            ['initiator_id' => $escrow['initiator_id']]
        );

        return $escrow;
    }

    /**
     * Activate escrow (both parties confirmed)
     */
    public function activateEscrow(string $escrowId): array
    {
        $escrow = $this->getEscrow($escrowId);

        if (!$escrow['initiator_confirmed'] || !$escrow['counterparty_confirmed']) {
            throw new \RuntimeException('Both parties must confirm before activation');
        }

        $this->validateStateTransition($escrow, self::STATE_ESCROW_ACTIVE);

        $escrow['state'] = self::STATE_ESCROW_ACTIVE;
        $escrow['activated_at'] = now()->toIso8601String();
        $escrow['execution_deadline'] = now()->addMinutes(self::EXECUTION_WINDOW_MINUTES)->toIso8601String();
        $escrow['state_history'][] = [
            'state' => self::STATE_ESCROW_ACTIVE,
            'timestamp' => now()->toIso8601String(),
            'actor_id' => 'system',
        ];

        $this->storeEscrow($escrow);

        $this->auditService->log(
            'escrow_activated',
            'escrow',
            $escrowId,
            null,
            [
                'execution_deadline' => $escrow['execution_deadline'],
            ]
        );

        return $escrow;
    }

    /**
     * Confirm party readiness
     */
    public function confirmAllParties(string $escrowId, User $user): array
    {
        $escrow = $this->getEscrow($escrowId);

        if ($user->id === $escrow['initiator_id']) {
            $escrow['initiator_confirmed'] = true;
        } elseif ($user->id === $escrow['counterparty_id']) {
            $escrow['counterparty_confirmed'] = true;
        } else {
            throw new \RuntimeException('User is not a party to this escrow');
        }

        $this->storeEscrow($escrow);

        $this->auditService->log(
            'escrow_party_confirmed',
            'escrow',
            $escrowId,
            $user,
            [
                'initiator_confirmed' => $escrow['initiator_confirmed'],
                'counterparty_confirmed' => $escrow['counterparty_confirmed'],
            ]
        );

        if ($escrow['initiator_confirmed'] && $escrow['counterparty_confirmed']) {
            return $this->activateEscrow($escrowId);
        }

        return $escrow;
    }

    /**
     * Dispatch execution
     */
    public function dispatchExecution(string $escrowId): array
    {
        $escrow = $this->getEscrow($escrowId);

        $this->validateStateTransition($escrow, self::STATE_EXECUTION_DISPATCHED);

        $escrow['state'] = self::STATE_EXECUTION_DISPATCHED;
        $escrow['dispatched_at'] = now()->toIso8601String();
        $escrow['state_history'][] = [
            'state' => self::STATE_EXECUTION_DISPATCHED,
            'timestamp' => now()->toIso8601String(),
            'actor_id' => 'system',
        ];

        $this->storeEscrow($escrow);

        $this->auditService->log(
            'escrow_execution_dispatched',
            'escrow',
            $escrowId,
            null,
            []
        );

        return $escrow;
    }

    /**
     * Complete escrow
     */
    public function completeEscrow(string $escrowId, array $executionResult): array
    {
        $escrow = $this->getEscrow($escrowId);

        $this->validateStateTransition($escrow, self::STATE_COMPLETED);

        $escrow['state'] = self::STATE_COMPLETED;
        $escrow['completed_at'] = now()->toIso8601String();
        $escrow['execution_result'] = $executionResult;
        $escrow['state_history'][] = [
            'state' => self::STATE_COMPLETED,
            'timestamp' => now()->toIso8601String(),
            'actor_id' => 'system',
        ];

        $this->storeEscrow($escrow);

        $this->auditService->log(
            'escrow_completed',
            'escrow',
            $escrowId,
            null,
            ['execution_result' => $executionResult]
        );

        return $escrow;
    }

    /**
     * Expire escrow if timeout reached
     */
    public function expireEscrow(string $escrowId): array
    {
        $escrow = $this->getEscrow($escrowId);

        if (in_array($escrow['state'], [self::STATE_COMPLETED, self::STATE_CANCELLED, self::STATE_EXPIRED])) {
            return $escrow;
        }

        $escrow['state'] = self::STATE_EXPIRED;
        $escrow['expired_at'] = now()->toIso8601String();
        $escrow['state_history'][] = [
            'state' => self::STATE_EXPIRED,
            'timestamp' => now()->toIso8601String(),
            'actor_id' => 'system',
            'reason' => 'timeout',
        ];

        $this->storeEscrow($escrow);

        $this->auditService->log(
            'escrow_expired',
            'escrow',
            $escrowId,
            null,
            ['reason' => 'timeout']
        );

        return $escrow;
    }

    /**
     * Cancel escrow
     */
    public function cancelEscrow(string $escrowId, User $canceller, string $reason): array
    {
        $escrow = $this->getEscrow($escrowId);

        if (in_array($escrow['state'], [self::STATE_COMPLETED, self::STATE_EXECUTION_DISPATCHED])) {
            throw new \RuntimeException('Cannot cancel escrow in current state');
        }

        $escrow['state'] = self::STATE_CANCELLED;
        $escrow['cancelled_at'] = now()->toIso8601String();
        $escrow['cancellation_reason'] = $reason;
        $escrow['cancelled_by'] = $canceller->id;
        $escrow['state_history'][] = [
            'state' => self::STATE_CANCELLED,
            'timestamp' => now()->toIso8601String(),
            'actor_id' => $canceller->id,
            'reason' => $reason,
        ];

        $this->storeEscrow($escrow);

        $this->auditService->log(
            'escrow_cancelled',
            'escrow',
            $escrowId,
            $canceller,
            ['reason' => $reason]
        );

        return $escrow;
    }

    /**
     * Check if escrow is expired
     */
    public function checkExpiry(string $escrowId): bool
    {
        $escrow = $this->getEscrow($escrowId);

        if (isset($escrow['expires_at']) && now()->isAfter($escrow['expires_at'])) {
            $this->expireEscrow($escrowId);
            return true;
        }

        if (isset($escrow['execution_deadline']) && 
            $escrow['state'] === self::STATE_ESCROW_ACTIVE &&
            now()->isAfter($escrow['execution_deadline'])) {
            $this->expireEscrow($escrowId);
            return true;
        }

        return false;
    }

    /**
     * Get escrow by ID
     */
    public function getEscrow(string $escrowId): array
    {
        $escrow = Cache::get("escrow:{$escrowId}");

        if (!$escrow) {
            throw new \RuntimeException('Escrow not found');
        }

        return $escrow;
    }

    /**
     * Store escrow in cache
     */
    private function storeEscrow(array $escrow): void
    {
        Cache::put("escrow:{$escrow['id']}", $escrow, now()->addHours(24));
    }

    /**
     * Generate unique escrow ID
     */
    private function generateEscrowId(): string
    {
        return 'ESC-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));
    }

    /**
     * Validate state transition
     */
    private function validateStateTransition(array $escrow, string $targetState): void
    {
        $allowedTransitions = [
            self::STATE_OFFER_OPEN => [self::STATE_PENDING_VALIDATION, self::STATE_CANCELLED, self::STATE_EXPIRED],
            self::STATE_PENDING_VALIDATION => [self::STATE_ESCROW_ACTIVE, self::STATE_CANCELLED, self::STATE_EXPIRED],
            self::STATE_ESCROW_ACTIVE => [self::STATE_EXECUTION_DISPATCHED, self::STATE_CANCELLED, self::STATE_EXPIRED],
            self::STATE_EXECUTION_DISPATCHED => [self::STATE_COMPLETED, self::STATE_EXPIRED],
        ];

        $currentState = $escrow['state'];

        if (!isset($allowedTransitions[$currentState]) || 
            !in_array($targetState, $allowedTransitions[$currentState])) {
            throw new \RuntimeException(
                "Invalid state transition from {$currentState} to {$targetState}"
            );
        }
    }
}

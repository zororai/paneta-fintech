<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeComment;
use App\Models\User;
use App\Models\TransactionIntent;
use App\Services\NotificationService;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DisputeEngine
{
    protected NotificationService $notifications;
    protected AuditService $audit;

    public function __construct(NotificationService $notifications, AuditService $audit)
    {
        $this->notifications = $notifications;
        $this->audit = $audit;
    }

    public function openDispute(
        User $user,
        string $disputeableType,
        int $disputeableId,
        string $type,
        float $amount,
        string $currency,
        string $description,
        string $priority = Dispute::PRIORITY_MEDIUM
    ): Dispute {
        return DB::transaction(function () use ($user, $disputeableType, $disputeableId, $type, $amount, $currency, $description, $priority) {
            $dispute = Dispute::create([
                'reference' => Dispute::generateReference(),
                'user_id' => $user->id,
                'disputable_type' => $disputeableType,
                'disputable_id' => $disputeableId,
                'status' => Dispute::STATUS_OPENED,
                'type' => $type,
                'priority' => $priority,
                'disputed_amount' => $amount,
                'currency' => $currency,
                'description' => $description,
                'evidence_deadline' => now()->addDays(14),
            ]);

            $this->audit->log(
                $user->id,
                'dispute_opened',
                'dispute',
                $dispute->id,
                [
                    'type' => $type,
                    'amount' => $amount,
                    'currency' => $currency,
                    'related_type' => $disputeableType,
                    'related_id' => $disputeableId,
                ]
            );

            Log::info('Dispute opened', [
                'dispute_id' => $dispute->id,
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
            ]);

            return $dispute;
        });
    }

    public function assignDispute(Dispute $dispute, User $assignee, User $assignedBy = null): Dispute
    {
        $dispute->update(['assigned_to' => $assignee->id]);

        $this->addComment($dispute, $assignedBy ?? $assignee, "Dispute assigned to {$assignee->name}", true);

        Log::info('Dispute assigned', [
            'dispute_id' => $dispute->id,
            'assigned_to' => $assignee->id,
        ]);

        return $dispute->fresh();
    }

    public function transitionStatus(Dispute $dispute, string $newStatus, User $user, string $reason = null): Dispute
    {
        if (!$dispute->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException("Cannot transition from {$dispute->status} to {$newStatus}");
        }

        $oldStatus = $dispute->status;
        $dispute->transitionTo($newStatus);

        if ($newStatus === Dispute::STATUS_ESCALATED) {
            $dispute->update(['escalated_at' => now()]);
        }

        $comment = "Status changed from {$oldStatus} to {$newStatus}";
        if ($reason) {
            $comment .= ": {$reason}";
        }
        $this->addComment($dispute, $user, $comment, true);

        $this->audit->log(
            $user->id,
            'dispute_status_changed',
            'dispute',
            $dispute->id,
            [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
            ]
        );

        return $dispute->fresh();
    }

    public function requestEvidence(Dispute $dispute, User $requestedBy, string $evidenceDescription): Dispute
    {
        $dispute = $this->transitionStatus($dispute, Dispute::STATUS_EVIDENCE_REQUESTED, $requestedBy);
        
        $dispute->update([
            'evidence_deadline' => now()->addDays(7),
        ]);

        $this->addComment($dispute, $requestedBy, "Evidence requested: {$evidenceDescription}", false);

        $this->notifications->send(
            $dispute->user,
            'dispute_evidence_requested',
            'Evidence Required for Your Dispute',
            "Please submit evidence for dispute {$dispute->reference} by " . $dispute->evidence_deadline->format('M d, Y'),
            route('paneta.disputes.show', $dispute),
            ['dispute_id' => $dispute->id, 'deadline' => $dispute->evidence_deadline->toIso8601String()]
        );

        return $dispute;
    }

    public function submitEvidence(
        Dispute $dispute,
        User $submitter,
        string $type,
        string $description,
        $file = null
    ): DisputeEvidence {
        $evidenceData = [
            'dispute_id' => $dispute->id,
            'submitted_by' => $submitter->id,
            'type' => $type,
            'description' => $description,
        ];

        if ($file) {
            $path = $file->store("disputes/{$dispute->id}/evidence", 'private');
            $evidenceData['file_path'] = $path;
            $evidenceData['file_name'] = $file->getClientOriginalName();
            $evidenceData['mime_type'] = $file->getMimeType();
        }

        $evidence = DisputeEvidence::create($evidenceData);

        if ($dispute->status === Dispute::STATUS_EVIDENCE_REQUESTED) {
            $this->transitionStatus($dispute, Dispute::STATUS_EVIDENCE_SUBMITTED, $submitter);
        }

        Log::info('Dispute evidence submitted', [
            'dispute_id' => $dispute->id,
            'evidence_id' => $evidence->id,
            'type' => $type,
        ]);

        return $evidence;
    }

    public function addComment(Dispute $dispute, User $user, string $comment, bool $isInternal = false): DisputeComment
    {
        return DisputeComment::create([
            'dispute_id' => $dispute->id,
            'user_id' => $user->id,
            'comment' => $comment,
            'is_internal' => $isInternal,
        ]);
    }

    public function resolveInFavor(Dispute $dispute, User $resolver, float $refundAmount = null, string $notes = null): Dispute
    {
        return $this->resolve($dispute, Dispute::STATUS_RESOLVED_IN_FAVOR, $resolver, $refundAmount, $notes);
    }

    public function resolveAgainst(Dispute $dispute, User $resolver, string $notes = null): Dispute
    {
        return $this->resolve($dispute, Dispute::STATUS_RESOLVED_AGAINST, $resolver, null, $notes);
    }

    protected function resolve(Dispute $dispute, string $resolution, User $resolver, float $amount = null, string $notes = null): Dispute
    {
        $dispute->resolve($resolution, $amount, $resolver, $notes);

        $this->audit->log(
            $resolver->id,
            'dispute_resolved',
            'dispute',
            $dispute->id,
            [
                'resolution' => $resolution,
                'refund_amount' => $amount,
                'notes' => $notes,
            ]
        );

        $notificationType = $resolution === Dispute::STATUS_RESOLVED_IN_FAVOR 
            ? 'dispute_resolved_favor' 
            : 'dispute_resolved_against';

        $this->notifications->send(
            $dispute->user,
            $notificationType,
            'Your Dispute Has Been Resolved',
            $resolution === Dispute::STATUS_RESOLVED_IN_FAVOR
                ? "Your dispute {$dispute->reference} has been resolved in your favor."
                : "Your dispute {$dispute->reference} has been reviewed and resolved.",
            route('paneta.disputes.show', $dispute),
            ['dispute_id' => $dispute->id, 'resolution' => $resolution, 'refund_amount' => $amount]
        );

        Log::info('Dispute resolved', [
            'dispute_id' => $dispute->id,
            'resolution' => $resolution,
            'refund_amount' => $amount,
        ]);

        return $dispute->fresh();
    }

    public function withdrawDispute(Dispute $dispute, User $user, string $reason = null): Dispute
    {
        if ($user->id !== $dispute->user_id && !$user->isAdmin()) {
            throw new \InvalidArgumentException('Only the dispute owner or admin can withdraw');
        }

        $dispute->transitionTo(Dispute::STATUS_WITHDRAWN);
        $this->addComment($dispute, $user, "Dispute withdrawn" . ($reason ? ": {$reason}" : ""), true);

        return $dispute->fresh();
    }

    public function expireOverdueDisputes(): int
    {
        $expired = Dispute::evidenceDeadlinePassed()->get();
        
        foreach ($expired as $dispute) {
            $dispute->update(['status' => Dispute::STATUS_EXPIRED]);
            
            $this->notifications->send(
                $dispute->user,
                'dispute_expired',
                'Dispute Expired',
                "Your dispute {$dispute->reference} has expired due to missing evidence.",
                route('paneta.disputes.show', $dispute)
            );
        }

        if ($expired->count() > 0) {
            Log::info('Expired overdue disputes', ['count' => $expired->count()]);
        }

        return $expired->count();
    }

    public function getDisputeStats(int $days = 30): array
    {
        $since = now()->subDays($days);

        $disputes = Dispute::where('created_at', '>=', $since)->get();

        return [
            'period_days' => $days,
            'total_opened' => $disputes->count(),
            'total_amount' => $disputes->sum('disputed_amount'),
            'by_status' => $disputes->groupBy('status')->map->count(),
            'by_type' => $disputes->groupBy('type')->map->count(),
            'by_priority' => $disputes->groupBy('priority')->map->count(),
            'resolved_in_favor' => $disputes->where('status', Dispute::STATUS_RESOLVED_IN_FAVOR)->count(),
            'resolved_against' => $disputes->where('status', Dispute::STATUS_RESOLVED_AGAINST)->count(),
            'avg_resolution_days' => $disputes->whereNotNull('resolved_at')
                ->avg(fn($d) => $d->created_at->diffInDays($d->resolved_at)),
            'refunded_amount' => $disputes->where('status', Dispute::STATUS_RESOLVED_IN_FAVOR)->sum('resolved_amount'),
        ];
    }

    public function getOpenDisputesForUser(User $user): array
    {
        return Dispute::where('user_id', $user->id)
            ->open()
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    public function getAssignedDisputes(User $assignee): array
    {
        return Dispute::assignedTo($assignee)
            ->open()
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }
}

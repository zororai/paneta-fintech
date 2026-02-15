<?php

namespace App\Listeners;

use App\Events\TransactionExecuted;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTransactionNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(TransactionExecuted $event): void
    {
        $this->notificationService->sendTransactionExecuted($event->user, [
            'id' => $event->transaction->id,
            'amount' => $event->transaction->amount,
            'currency' => $event->transaction->currency,
            'reference' => $event->transaction->reference,
            'status' => $event->transaction->status,
        ]);
    }
}

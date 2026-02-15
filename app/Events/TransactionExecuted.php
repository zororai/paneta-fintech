<?php

namespace App\Events;

use App\Models\TransactionIntent;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionExecuted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public TransactionIntent $transaction;
    public User $user;

    public function __construct(TransactionIntent $transaction, User $user)
    {
        $this->transaction = $transaction;
        $this->user = $user;
    }
}

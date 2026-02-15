<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SuspiciousActivityDetected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?User $user;
    public string $reason;
    public array $metadata;

    public function __construct(?User $user, string $reason, array $metadata = [])
    {
        $this->user = $user;
        $this->reason = $reason;
        $this->metadata = $metadata;
    }
}

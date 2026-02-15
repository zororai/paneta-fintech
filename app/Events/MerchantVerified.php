<?php

namespace App\Events;

use App\Models\Merchant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MerchantVerified
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Merchant $merchant;

    public function __construct(Merchant $merchant)
    {
        $this->merchant = $merchant;
    }
}

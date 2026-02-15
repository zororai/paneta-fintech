<?php

namespace App\Events;

use App\Models\FxQuote;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FxQuoteExpired
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public FxQuote $quote;

    public function __construct(FxQuote $quote)
    {
        $this->quote = $quote;
    }
}

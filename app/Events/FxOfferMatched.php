<?php

namespace App\Events;

use App\Models\FxOffer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FxOfferMatched
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public FxOffer $offer;
    public FxOffer $counterOffer;

    public function __construct(FxOffer $offer, FxOffer $counterOffer)
    {
        $this->offer = $offer;
        $this->counterOffer = $counterOffer;
    }
}

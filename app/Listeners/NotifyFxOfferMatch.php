<?php

namespace App\Listeners;

use App\Events\FxOfferMatched;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyFxOfferMatch implements ShouldQueue
{
    public string $queue = 'notifications';

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(FxOfferMatched $event): void
    {
        $this->notificationService->sendFxOfferMatched($event->offer->user, [
            'id' => $event->offer->id,
            'amount' => $event->offer->amount,
            'sell_currency' => $event->offer->sell_currency,
            'buy_currency' => $event->offer->buy_currency,
            'rate' => $event->offer->rate,
        ]);

        $this->notificationService->sendFxOfferMatched($event->counterOffer->user, [
            'id' => $event->counterOffer->id,
            'amount' => $event->counterOffer->amount,
            'sell_currency' => $event->counterOffer->sell_currency,
            'buy_currency' => $event->counterOffer->buy_currency,
            'rate' => $event->counterOffer->rate,
        ]);
    }
}

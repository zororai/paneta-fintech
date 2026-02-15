<?php

namespace App\Services;

use App\Models\FxOffer;
use App\Models\LinkedAccount;
use Illuminate\Support\Facades\DB;

class SmartEscrowEngine
{
    public function executeAtomicSwap(FxOffer $offer, FxOffer $counterOffer): EscrowResult
    {
        $offerAmount = min($offer->getRemainingAmount(), $counterOffer->getRemainingAmount() * $counterOffer->rate);
        $counterAmount = $offerAmount / $counterOffer->rate;

        try {
            return DB::transaction(function () use ($offer, $counterOffer, $offerAmount, $counterAmount) {
                $offerAccount = LinkedAccount::lockForUpdate()->find($offer->source_account_id);
                $counterAccount = LinkedAccount::lockForUpdate()->find($counterOffer->source_account_id);

                if ($offerAccount->mock_balance < $offerAmount) {
                    return new EscrowResult(
                        success: false,
                        error: 'Insufficient balance in offer account'
                    );
                }

                if ($counterAccount->mock_balance < $counterAmount) {
                    return new EscrowResult(
                        success: false,
                        error: 'Insufficient balance in counter-offer account'
                    );
                }

                $offerAccount->decrement('mock_balance', $offerAmount);
                $counterAccount->decrement('mock_balance', $counterAmount);

                $offerAccount->increment('mock_balance', $counterAmount);
                $counterAccount->increment('mock_balance', $offerAmount);

                $offer->increment('filled_amount', $offerAmount);
                $counterOffer->increment('filled_amount', $counterAmount);

                return new EscrowResult(
                    success: true,
                    offerAmountTransferred: $offerAmount,
                    counterAmountTransferred: $counterAmount
                );
            });
        } catch (\Exception $e) {
            return new EscrowResult(
                success: false,
                error: $e->getMessage()
            );
        }
    }

    public function validateSwapPreconditions(FxOffer $offer, FxOffer $counterOffer): array
    {
        $errors = [];

        if ($offer->sell_currency !== $counterOffer->buy_currency) {
            $errors[] = 'Currency pair mismatch: offer sell vs counter buy';
        }

        if ($offer->buy_currency !== $counterOffer->sell_currency) {
            $errors[] = 'Currency pair mismatch: offer buy vs counter sell';
        }

        if ($offer->user_id === $counterOffer->user_id) {
            $errors[] = 'Cannot swap with self';
        }

        $offerAccount = $offer->sourceAccount;
        $counterAccount = $counterOffer->sourceAccount;

        if (!$offerAccount || $offerAccount->status !== 'active') {
            $errors[] = 'Offer source account is not active';
        }

        if (!$counterAccount || $counterAccount->status !== 'active') {
            $errors[] = 'Counter-offer source account is not active';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function calculateSwapAmounts(FxOffer $offer, FxOffer $counterOffer): array
    {
        $maxOfferAmount = $offer->getRemainingAmount();
        $maxCounterAmount = $counterOffer->getRemainingAmount();

        $effectiveRate = ($offer->rate + (1 / $counterOffer->rate)) / 2;

        $offerAmountAtCounterRate = $maxCounterAmount * $counterOffer->rate;
        $counterAmountAtOfferRate = $maxOfferAmount / $offer->rate;

        $actualOfferAmount = min($maxOfferAmount, $offerAmountAtCounterRate);
        $actualCounterAmount = min($maxCounterAmount, $counterAmountAtOfferRate);

        return [
            'offer_amount' => round($actualOfferAmount, 2),
            'counter_amount' => round($actualCounterAmount, 2),
            'effective_rate' => round($effectiveRate, 8),
            'offer_remaining_after' => $maxOfferAmount - $actualOfferAmount,
            'counter_remaining_after' => $maxCounterAmount - $actualCounterAmount,
        ];
    }

    public function rollbackSwap(FxOffer $offer, FxOffer $counterOffer, float $offerAmount, float $counterAmount): bool
    {
        try {
            return DB::transaction(function () use ($offer, $counterOffer, $offerAmount, $counterAmount) {
                $offerAccount = LinkedAccount::lockForUpdate()->find($offer->source_account_id);
                $counterAccount = LinkedAccount::lockForUpdate()->find($counterOffer->source_account_id);

                $offerAccount->increment('mock_balance', $offerAmount);
                $offerAccount->decrement('mock_balance', $counterAmount);

                $counterAccount->increment('mock_balance', $counterAmount);
                $counterAccount->decrement('mock_balance', $offerAmount);

                $offer->decrement('filled_amount', $offerAmount);
                $counterOffer->decrement('filled_amount', $counterAmount);

                return true;
            });
        } catch (\Exception $e) {
            return false;
        }
    }
}

class EscrowResult
{
    public function __construct(
        public bool $success,
        public ?string $error = null,
        public float $offerAmountTransferred = 0,
        public float $counterAmountTransferred = 0
    ) {}
}

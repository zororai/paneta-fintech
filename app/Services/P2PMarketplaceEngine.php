<?php

namespace App\Services;

use App\Models\FxOffer;
use App\Models\LinkedAccount;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class P2PMarketplaceEngine
{
    public function __construct(
        protected SmartEscrowEngine $escrowEngine,
        protected FeeEngine $feeEngine,
        protected AuditService $auditService
    ) {}

    public function createOffer(
        User $user,
        LinkedAccount $sourceAccount,
        string $sellCurrency,
        string $buyCurrency,
        float $rate,
        float $amount,
        ?float $minAmount = null,
        ?int $expiresInDays = 7,
        ?string $idempotencyKey = null,
        ?LinkedAccount $destinationAccount = null,
        array $settlementMethods = []
    ): FxOfferResult {
        if ($idempotencyKey) {
            $existing = FxOffer::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return new FxOfferResult(success: true, offer: $existing, idempotentReplay: true);
            }
        }

        if ($sourceAccount->user_id !== $user->id) {
            return new FxOfferResult(success: false, error: 'Account does not belong to user');
        }

        if ($destinationAccount && $destinationAccount->user_id !== $user->id) {
            return new FxOfferResult(success: false, error: 'Destination account does not belong to user');
        }

        if ($sourceAccount->currency !== $sellCurrency) {
            return new FxOfferResult(success: false, error: 'Source account currency mismatch');
        }

        if ($sourceAccount->mock_balance < $amount) {
            return new FxOfferResult(success: false, error: 'Insufficient balance');
        }

        $offer = FxOffer::create([
            'user_id' => $user->id,
            'source_account_id' => $sourceAccount->id,
            'destination_account_id' => $destinationAccount?->id,
            'sell_currency' => $sellCurrency,
            'buy_currency' => $buyCurrency,
            'rate' => $rate,
            'amount' => $amount,
            'min_amount' => $minAmount,
            'filled_amount' => 0,
            'status' => 'open',
            'expires_at' => $expiresInDays ? now()->addDays($expiresInDays) : null,
            'settlement_methods' => $settlementMethods,
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->auditService->log(
            'fx_offer_created',
            'FxOffer',
            $offer->id,
            $user,
            [
                'sell_currency' => $sellCurrency,
                'buy_currency' => $buyCurrency,
                'rate' => $rate,
                'amount' => $amount,
            ]
        );

        return new FxOfferResult(success: true, offer: $offer);
    }

    public function findMatchingOffers(FxOffer $offer): Collection
    {
        return FxOffer::open()
            ->where('sell_currency', $offer->buy_currency)
            ->where('buy_currency', $offer->sell_currency)
            ->where('user_id', '!=', $offer->user_id)
            ->where('rate', '>=', 1 / $offer->rate * 0.99)
            ->orderBy('rate', 'desc')
            ->get();
    }

    public function matchOffers(FxOffer $offer, FxOffer $counterOffer): MatchResult
    {
        if (!$offer->canMatch($counterOffer)) {
            return new MatchResult(success: false, error: 'Offers cannot be matched');
        }

        $matchAmount = min($offer->getRemainingAmount(), $counterOffer->getRemainingAmount());

        if ($offer->min_amount && $matchAmount < $offer->min_amount) {
            return new MatchResult(success: false, error: 'Match amount below minimum');
        }

        if ($counterOffer->min_amount && $matchAmount < $counterOffer->min_amount) {
            return new MatchResult(success: false, error: 'Match amount below counter-offer minimum');
        }

        try {
            return DB::transaction(function () use ($offer, $counterOffer, $matchAmount) {
                $offer->update([
                    'matched_offer_id' => $counterOffer->id,
                    'matched_user_id' => $counterOffer->user_id,
                ]);
                $offer->transitionTo('matched');

                $counterOffer->update([
                    'matched_offer_id' => $offer->id,
                    'matched_user_id' => $offer->user_id,
                ]);
                $counterOffer->transitionTo('matched');

                $this->auditService->log(
                    $offer->user_id,
                    'fx_offers_matched',
                    'FxOffer',
                    $offer->id,
                    [
                        'counter_offer_id' => $counterOffer->id,
                        'match_amount' => $matchAmount,
                    ]
                );

                return new MatchResult(
                    success: true,
                    offer: $offer->fresh(),
                    counterOffer: $counterOffer->fresh(),
                    matchAmount: $matchAmount
                );
            });
        } catch (\Exception $e) {
            return new MatchResult(success: false, error: $e->getMessage());
        }
    }

    public function executeMatch(FxOffer $offer, FxOffer $counterOffer): ExecutionResult
    {
        if ($offer->status !== 'matched' || $counterOffer->status !== 'matched') {
            return new ExecutionResult(success: false, error: 'Offers must be in matched state');
        }

        try {
            return DB::transaction(function () use ($offer, $counterOffer) {
                $escrowResult = $this->escrowEngine->executeAtomicSwap($offer, $counterOffer);

                if (!$escrowResult->success) {
                    $offer->transitionTo('failed');
                    $counterOffer->transitionTo('failed');
                    return new ExecutionResult(success: false, error: $escrowResult->error);
                }

                $offer->transitionTo('executed');
                $counterOffer->transitionTo('executed');

                $fee1 = $this->feeEngine->calculateFee($offer->amount, 'p2p_fx');
                $fee2 = $this->feeEngine->calculateFee($counterOffer->amount, 'p2p_fx');

                $this->feeEngine->recordFee($offer->user, 'p2p_fx', $offer->id, $fee1, $offer->sell_currency, 'p2p_fx');
                $this->feeEngine->recordFee($counterOffer->user, 'p2p_fx', $counterOffer->id, $fee2, $counterOffer->sell_currency, 'p2p_fx');

                $this->auditService->log(
                    $offer->user_id,
                    'fx_offer_executed',
                    'FxOffer',
                    $offer->id,
                    ['counter_offer_id' => $counterOffer->id]
                );

                return new ExecutionResult(
                    success: true,
                    offer: $offer->fresh(),
                    counterOffer: $counterOffer->fresh()
                );
            });
        } catch (\Exception $e) {
            return new ExecutionResult(success: false, error: $e->getMessage());
        }
    }

    public function cancelOffer(FxOffer $offer, User $user): bool
    {
        if ($offer->user_id !== $user->id) {
            return false;
        }

        if (!in_array($offer->status, ['open', 'partially_filled'])) {
            return false;
        }

        $offer->transitionTo('cancelled');

        $this->auditService->log(
            'fx_offer_cancelled',
            'FxOffer',
            $offer->id,
            $user,
            []
        );

        return true;
    }

    public function getOpenOffers(string $sellCurrency, string $buyCurrency): Collection
    {
        return FxOffer::open()
            ->forPair($sellCurrency, $buyCurrency)
            ->orderBy('rate', 'desc')
            ->with('user')
            ->get();
    }

    public function getUserOffers(User $user): Collection
    {
        return FxOffer::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function expireOldOffers(): int
    {
        $expired = FxOffer::open()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $offer) {
            $offer->update(['status' => 'expired']);
        }

        return $expired->count();
    }
}

class FxOfferResult
{
    public function __construct(
        public bool $success,
        public ?FxOffer $offer = null,
        public ?string $error = null,
        public bool $idempotentReplay = false
    ) {}
}

class MatchResult
{
    public function __construct(
        public bool $success,
        public ?FxOffer $offer = null,
        public ?FxOffer $counterOffer = null,
        public float $matchAmount = 0,
        public ?string $error = null
    ) {}
}

class ExecutionResult
{
    public function __construct(
        public bool $success,
        public ?FxOffer $offer = null,
        public ?FxOffer $counterOffer = null,
        public ?string $error = null
    ) {}
}

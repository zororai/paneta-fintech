<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\FxOffer;
use App\Models\LinkedAccount;
use App\Services\P2PMarketplaceEngine;
use App\Services\SmartEscrowEngine;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class P2PEscrowController extends Controller
{
    public function __construct(
        protected P2PMarketplaceEngine $marketplaceEngine,
        protected SmartEscrowEngine $escrowEngine,
        protected AuditService $auditService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        
        $myOffers = FxOffer::where('user_id', $user->id)
            ->with(['sourceAccount.institution', 'matchedOffer.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        $linkedAccounts = LinkedAccount::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('institution')
            ->get();

        $currencies = ['USD', 'EUR', 'GBP', 'ZAR', 'ZWL', 'BWP', 'KES', 'NGN'];

        $stats = [
            'active_offers' => FxOffer::where('user_id', $user->id)->whereIn('status', ['open', 'matched'])->count(),
            'completed_swaps' => FxOffer::where('user_id', $user->id)->where('status', 'executed')->count(),
            'total_volume' => FxOffer::where('user_id', $user->id)->where('status', 'executed')->sum('filled_amount'),
        ];

        return Inertia::render('Paneta/P2PEscrow', [
            'myOffers' => $myOffers,
            'linkedAccounts' => $linkedAccounts,
            'currencies' => $currencies,
            'stats' => $stats,
        ]);
    }

    public function createOffer(Request $request)
    {
        $validated = $request->validate([
            'source_account_id' => 'required|exists:linked_accounts,id',
            'destination_account_id' => 'nullable|exists:linked_accounts,id',
            'sell_currency' => 'required|string|size:3',
            'buy_currency' => 'required|string|size:3',
            'rate' => 'required|numeric|min:0.000001',
            'amount' => 'required|numeric|min:1',
            'min_amount' => 'nullable|numeric|min:1',
            'settlement_methods' => 'required|array|min:1',
            'settlement_methods.*' => 'string|in:bank,mobile_wallet,card',
            'expires_in_days' => 'nullable|integer|min:1|max:90',
        ]);

        $sourceAccount = LinkedAccount::findOrFail($validated['source_account_id']);
        if ($sourceAccount->user_id !== $request->user()->id) {
            return back()->withErrors(['source_account_id' => 'Account does not belong to you']);
        }

        $destinationAccount = null;
        if (isset($validated['destination_account_id'])) {
            $destinationAccount = LinkedAccount::findOrFail($validated['destination_account_id']);
            if ($destinationAccount->user_id !== $request->user()->id) {
                return back()->withErrors(['destination_account_id' => 'Account does not belong to you']);
            }
        }

        $result = $this->marketplaceEngine->createOffer(
            user: $request->user(),
            sourceAccount: $sourceAccount,
            sellCurrency: $validated['sell_currency'],
            buyCurrency: $validated['buy_currency'],
            rate: $validated['rate'],
            amount: $validated['amount'],
            minAmount: $validated['min_amount'] ?? null,
            expiresInDays: $validated['expires_in_days'] ?? 7,
            idempotencyKey: null,
            destinationAccount: $destinationAccount,
            settlementMethods: $validated['settlement_methods']
        );

        if ($result->success) {
            return back()->with('success', 'P2P offer created successfully and is now live on the marketplace.');
        }

        return back()->withErrors(['error' => $result->error]);
    }

    public function cancelOffer(Request $request, FxOffer $offer)
    {
        if ($offer->user_id !== $request->user()->id) {
            abort(403);
        }

        $result = $this->marketplaceEngine->cancelOffer($offer, $request->user());

        if ($result) {
            return back()->with('success', 'Offer cancelled.');
        }

        return back()->withErrors(['error' => 'Cannot cancel this offer.']);
    }

    public function findMatches(Request $request, FxOffer $offer)
    {
        if ($offer->user_id !== $request->user()->id) {
            abort(403);
        }

        $matches = $this->marketplaceEngine->findMatchingOffers($offer);

        return response()->json([
            'matches' => $matches->map(fn($m) => [
                'id' => $m->id,
                'user' => $m->user->name ?? 'Anonymous',
                'rate' => $m->rate,
                'amount' => $m->getRemainingAmount(),
                'sell_currency' => $m->sell_currency,
                'buy_currency' => $m->buy_currency,
            ]),
        ]);
    }

    public function acceptMatch(Request $request, FxOffer $offer, FxOffer $counterOffer)
    {
        if ($offer->user_id !== $request->user()->id) {
            abort(403);
        }

        $matchResult = $this->marketplaceEngine->matchOffers($offer, $counterOffer);

        if (!$matchResult->success) {
            return back()->withErrors(['error' => $matchResult->error]);
        }

        $executionResult = $this->marketplaceEngine->executeMatch($offer, $counterOffer);

        if ($executionResult->success) {
            return back()->with('success', 'FX swap executed successfully!');
        }

        return back()->withErrors(['error' => $executionResult->error]);
    }
}

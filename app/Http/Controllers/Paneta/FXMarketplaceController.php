<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\FxOffer;
use App\Models\LinkedAccount;
use App\Services\P2PMarketplaceEngine;
use App\Services\FXRFQBroadcastService;
use App\Services\FXDiscoveryEngine;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FXMarketplaceController extends Controller
{
    public function __construct(
        protected P2PMarketplaceEngine $marketplaceEngine,
        protected FXDiscoveryEngine $fxDiscovery,
        protected AuditService $auditService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        
        $currencies = ['USD', 'EUR', 'GBP', 'ZAR', 'ZWL', 'BWP', 'KES', 'NGN'];
        
        $openOffers = [];
        foreach (['USD/ZAR', 'EUR/USD', 'GBP/USD', 'USD/ZWL'] as $pair) {
            [$sell, $buy] = explode('/', $pair);
            $offers = $this->marketplaceEngine->getOpenOffers($sell, $buy);
            if ($offers->isNotEmpty()) {
                $openOffers[$pair] = $offers->take(5)->map(fn($o) => [
                    'id' => $o->id,
                    'rate' => $o->rate,
                    'amount' => $o->getRemainingAmount(),
                    'min_amount' => $o->min_amount,
                    'user' => $o->user->name ?? 'Anonymous',
                    'expires_at' => $o->expires_at,
                ]);
            }
        }

        $linkedAccounts = LinkedAccount::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('institution')
            ->get();

        $marketStats = [
            'total_open_offers' => FxOffer::open()->count(),
            'total_pairs' => count($openOffers),
            'today_volume' => FxOffer::where('status', 'executed')
                ->whereDate('updated_at', today())
                ->sum('filled_amount'),
        ];

        return Inertia::render('Paneta/FXMarketplace', [
            'openOffers' => $openOffers,
            'linkedAccounts' => $linkedAccounts,
            'currencies' => $currencies,
            'marketStats' => $marketStats,
        ]);
    }

    public function getOrderBook(Request $request)
    {
        $validated = $request->validate([
            'sell_currency' => 'required|string|size:3',
            'buy_currency' => 'required|string|size:3',
        ]);

        $offers = $this->marketplaceEngine->getOpenOffers(
            $validated['sell_currency'],
            $validated['buy_currency']
        );

        return response()->json([
            'offers' => $offers->map(fn($o) => [
                'id' => $o->id,
                'rate' => $o->rate,
                'amount' => $o->getRemainingAmount(),
                'min_amount' => $o->min_amount,
                'expires_at' => $o->expires_at,
            ]),
        ]);
    }

    public function takeOffer(Request $request, FxOffer $offer)
    {
        $validated = $request->validate([
            'source_account_id' => 'required|exists:linked_accounts,id',
            'amount' => 'nullable|numeric|min:1',
        ]);

        $sourceAccount = LinkedAccount::findOrFail($validated['source_account_id']);
        if ($sourceAccount->user_id !== $request->user()->id) {
            return back()->withErrors(['source_account_id' => 'Account does not belong to you']);
        }

        if ($offer->user_id === $request->user()->id) {
            return back()->withErrors(['error' => 'Cannot take your own offer']);
        }

        $counterOffer = $this->marketplaceEngine->createOffer(
            user: $request->user(),
            sourceAccount: $sourceAccount,
            sellCurrency: $offer->buy_currency,
            buyCurrency: $offer->sell_currency,
            rate: 1 / $offer->rate,
            amount: $validated['amount'] ?? $offer->getRemainingAmount(),
            expiresInHours: 1
        );

        if (!$counterOffer->success) {
            return back()->withErrors(['error' => $counterOffer->error]);
        }

        $matchResult = $this->marketplaceEngine->matchOffers($offer, $counterOffer->offer);

        if (!$matchResult->success) {
            return back()->withErrors(['error' => $matchResult->error]);
        }

        $executionResult = $this->marketplaceEngine->executeMatch($offer, $counterOffer->offer);

        if ($executionResult->success) {
            return back()->with('success', 'FX swap executed successfully!');
        }

        return back()->withErrors(['error' => $executionResult->error]);
    }

    public function executeExchange(Request $request)
    {
        $validated = $request->validate([
            'provider_id' => 'required|exists:institutions,id',
            'source_account_id' => 'required|exists:linked_accounts,id',
            'destination_account_id' => 'required|exists:linked_accounts,id',
            'source_currency' => 'required|string|size:3',
            'destination_currency' => 'required|string|size:3',
            'amount' => 'required|numeric|min:1',
            'settlement_preference' => 'required|string|in:instant,standard,economy',
        ]);

        $user = $request->user();

        // Validate account ownership
        $sourceAccount = LinkedAccount::findOrFail($validated['source_account_id']);
        $destinationAccount = LinkedAccount::findOrFail($validated['destination_account_id']);

        if ($sourceAccount->user_id !== $user->id || $destinationAccount->user_id !== $user->id) {
            return back()->withErrors(['error' => 'Accounts do not belong to you']);
        }

        // Calculate exchange rate and fees
        $exchangeRate = $this->calculateExchangeRate($validated['source_currency'], $validated['destination_currency']);
        $destinationAmount = $validated['amount'] * $exchangeRate;
        $panetaFee = $validated['amount'] * 0.0099;
        $providerFee = $validated['amount'] * 0.002;
        $totalFees = $panetaFee + $providerFee;
        $netAmount = $validated['amount'] + $totalFees;

        // Create FX transaction record
        $fxTransaction = \App\Models\FxTransaction::create([
            'user_id' => $user->id,
            'provider_id' => $validated['provider_id'],
            'source_account_id' => $validated['source_account_id'],
            'destination_account_id' => $validated['destination_account_id'],
            'source_currency' => $validated['source_currency'],
            'destination_currency' => $validated['destination_currency'],
            'source_amount' => $validated['amount'],
            'exchange_rate' => $exchangeRate,
            'destination_amount' => $destinationAmount,
            'paneta_fee' => $panetaFee,
            'provider_fee' => $providerFee,
            'total_fees' => $totalFees,
            'net_amount_debited' => $netAmount,
            'settlement_preference' => $validated['settlement_preference'],
            'status' => 'pending',
            'transaction_type' => 'fx_marketplace',
        ]);

        // Audit log
        $this->auditService->log(
            'fx_marketplace_exchange_initiated',
            'fx_transaction',
            $fxTransaction->id,
            $user,
            [
                'provider_id' => $validated['provider_id'],
                'source_currency' => $validated['source_currency'],
                'destination_currency' => $validated['destination_currency'],
                'amount' => $validated['amount'],
                'exchange_rate' => $exchangeRate,
            ]
        );

        return back()->with('success', 'Exchange initiated successfully! Transaction ID: ' . $fxTransaction->id);
    }

    private function calculateExchangeRate(string $from, string $to): float
    {
        // Mock exchange rate calculation
        $rates = [
            'USD-EUR' => 0.85,
            'EUR-USD' => 1.18,
            'USD-GBP' => 0.79,
            'GBP-USD' => 1.27,
            'USD-ZAR' => 18.5,
            'ZAR-USD' => 0.054,
        ];
        
        $key = "$from-$to";
        return $rates[$key] ?? 1.0;
    }
}

<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\LinkedAccount;
use App\Models\FxQuote;
use App\Models\FxOffer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyExchangeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $linkedAccounts = $user->linkedAccounts()
            ->with('institution')
            ->where('status', 'active')
            ->get();

        $fxProviders = Institution::active()
            ->fxProviders()
            ->get();

        $recentQuotes = FxQuote::where('user_id', $user->id)
            ->with('institution')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $currencies = ['USD', 'EUR', 'GBP', 'ZAR', 'ZWL', 'KES', 'NGN'];

        // Get active P2P offers with user information
        $p2pOffers = FxOffer::with('user')
            ->where('status', 'open')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($offer) {
                return [
                    'id' => $offer->id,
                    'user' => [
                        'name' => $offer->user->name,
                        'location' => 'Zimbabwe', // Mock data
                        'trust_score' => 4.9, // Mock data
                        'total_trades' => rand(100, 2000), // Mock data
                    ],
                    'sell_currency' => $offer->sell_currency,
                    'buy_currency' => $offer->buy_currency,
                    'rate' => (float) $offer->rate,
                    'amount' => (float) $offer->amount,
                    'min_amount' => $offer->min_amount ? (float) $offer->min_amount : 100,
                    'max_amount' => (float) $offer->amount,
                    'settlement_methods' => $offer->settlement_methods ?? [],
                    'expires_at' => $offer->expires_at ? $offer->expires_at->toISOString() : null,
                    'status' => $offer->status,
                ];
            })
            ->values()
            ->toArray();

        return Inertia::render('Paneta/CurrencyExchange', [
            'linkedAccounts' => $linkedAccounts,
            'fxProviders' => $fxProviders,
            'recentQuotes' => $recentQuotes,
            'currencies' => $currencies,
            'panetaFeePercent' => 0.99,
            'p2pOffers' => $p2pOffers,
        ]);
    }

    public function getQuote(Request $request)
    {
        $validated = $request->validate([
            'source_account_id' => ['required', 'exists:linked_accounts,id'],
            'source_currency' => ['required', 'string', 'size:3'],
            'destination_currency' => ['required', 'string', 'size:3'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $user = $request->user();
        $fxProviders = Institution::active()->fxProviders()->get();

        $quotes = [];
        foreach ($fxProviders as $provider) {
            $rate = $this->getMockRate($validated['source_currency'], $validated['destination_currency']);
            $spread = rand(10, 50) / 10000;
            $fee = $validated['amount'] * 0.0099;
            $destinationAmount = ($validated['amount'] - $fee) * $rate;

            $quote = FxQuote::create([
                'user_id' => $user->id,
                'institution_id' => $provider->id,
                'source_currency' => $validated['source_currency'],
                'destination_currency' => $validated['destination_currency'],
                'source_amount' => $validated['amount'],
                'rate' => $rate,
                'spread' => $spread,
                'fee' => $fee,
                'destination_amount' => $destinationAmount,
                'status' => 'pending',
                'expires_at' => now()->addMinutes(5),
            ]);

            $quotes[] = [
                'id' => $quote->id,
                'provider' => $provider->name,
                'rate' => $rate,
                'spread' => $spread,
                'fee' => $fee,
                'destination_amount' => $destinationAmount,
                'eta' => rand(1, 3) . ' business days',
                'expires_at' => $quote->expires_at,
            ];
        }

        usort($quotes, fn($a, $b) => $b['destination_amount'] <=> $a['destination_amount']);

        return response()->json([
            'success' => true,
            'quotes' => $quotes,
        ]);
    }

    private function getMockRate(string $from, string $to): float
    {
        $rates = [
            'USD' => 1.0,
            'EUR' => 0.92,
            'GBP' => 0.79,
            'ZAR' => 18.5,
            'ZWL' => 322.0,
            'KES' => 153.0,
            'NGN' => 1550.0,
        ];

        $fromRate = $rates[$from] ?? 1.0;
        $toRate = $rates[$to] ?? 1.0;

        return $toRate / $fromRate;
    }
}

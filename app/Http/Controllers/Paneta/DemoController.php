<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\FxOffer;
use App\Models\PaymentRequest;
use App\Services\DemoSimulationService;
use Illuminate\Http\Request;

class DemoController extends Controller
{
    public function __construct(
        protected DemoSimulationService $demoService
    ) {}

    public function simulateAcceptOffer(Request $request, FxOffer $offer)
    {
        if ($offer->user_id !== $request->user()->id) {
            abort(403);
        }

        if (!$this->demoService->isDemoMode()) {
            return back()->withErrors(['error' => 'Demo mode is not enabled']);
        }

        $result = $this->demoService->simulateAcceptOffer($offer);

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->withErrors(['error' => $result['error']]);
    }

    public function simulatePayRequest(Request $request, PaymentRequest $paymentRequest)
    {
        if ($paymentRequest->user_id !== $request->user()->id) {
            abort(403);
        }

        if (!$this->demoService->isDemoMode()) {
            return back()->withErrors(['error' => 'Demo mode is not enabled']);
        }

        $result = $this->demoService->simulatePayRequest($paymentRequest);

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->withErrors(['error' => $result['error']]);
    }

    public function seedMarketplace(Request $request)
    {
        if (!$this->demoService->isDemoMode()) {
            return back()->withErrors(['error' => 'Demo mode is not enabled']);
        }

        $validated = $request->validate([
            'sell_currency' => 'required|string|size:3',
            'buy_currency' => 'required|string|size:3',
            'count' => 'nullable|integer|min:1|max:10',
        ]);

        $offers = $this->demoService->createDemoCounterOffers(
            $validated['sell_currency'],
            $validated['buy_currency'],
            $validated['count'] ?? 3
        );

        return back()->with('success', count($offers) . ' demo offers created');
    }

    public function status()
    {
        return response()->json([
            'demo_mode' => $this->demoService->isDemoMode(),
            'demo_user' => $this->demoService->getDemoUser()->only(['id', 'name', 'email']),
        ]);
    }
}

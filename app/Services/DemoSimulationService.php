<?php

namespace App\Services;

use App\Models\User;
use App\Models\LinkedAccount;
use App\Models\FxOffer;
use App\Models\PaymentRequest;
use App\Models\Institution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSimulationService
{
    protected const DEMO_USER_EMAIL = 'demo-counterparty@paneta.test';
    protected const DEMO_USER_NAME = 'Demo Counterparty';

    public function __construct(
        protected P2PMarketplaceEngine $marketplaceEngine,
        protected PaymentRequestEngine $paymentRequestEngine,
        protected AuditService $auditService
    ) {}

    public function getDemoUser(): User
    {
        return User::firstOrCreate(
            ['email' => self::DEMO_USER_EMAIL],
            [
                'name' => self::DEMO_USER_NAME,
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
            ]
        );
    }

    public function ensureDemoUserHasAccounts(): array
    {
        $demoUser = $this->getDemoUser();
        $accounts = [];

        $currencies = ['USD', 'EUR', 'GBP', 'ZAR', 'ZWL', 'BWP', 'KES', 'NGN'];
        $institution = Institution::first();

        if (!$institution) {
            $institution = Institution::create([
                'name' => 'Demo Bank',
                'code' => 'DEMO',
                'country' => 'US',
                'logo_url' => null,
                'supported_currencies' => $currencies,
                'is_active' => true,
            ]);
        }

        foreach ($currencies as $currency) {
            $account = LinkedAccount::firstOrCreate(
                [
                    'user_id' => $demoUser->id,
                    'currency' => $currency,
                ],
                [
                    'institution_id' => $institution->id,
                    'account_identifier' => 'DEMO-' . $currency . '-' . Str::random(8),
                    'mock_balance' => rand(10000, 100000),
                    'consent_token' => Str::random(64),
                    'consent_expires_at' => now()->addYear(),
                    'status' => 'active',
                ]
            );
            $accounts[$currency] = $account;
        }

        return $accounts;
    }

    public function simulateAcceptOffer(FxOffer $offer): array
    {
        if ($offer->status !== 'open') {
            return [
                'success' => false,
                'error' => 'Offer is not open for matching',
            ];
        }

        $demoUser = $this->getDemoUser();
        $accounts = $this->ensureDemoUserHasAccounts();

        $counterCurrency = $offer->buy_currency;
        if (!isset($accounts[$counterCurrency])) {
            return [
                'success' => false,
                'error' => "Demo user has no {$counterCurrency} account",
            ];
        }

        $sourceAccount = $accounts[$counterCurrency];

        try {
            DB::beginTransaction();

            $counterOffer = FxOffer::create([
                'user_id' => $demoUser->id,
                'source_account_id' => $sourceAccount->id,
                'sell_currency' => $offer->buy_currency,
                'buy_currency' => $offer->sell_currency,
                'rate' => 1 / $offer->rate,
                'amount' => $offer->amount * $offer->rate,
                'filled_amount' => 0,
                'min_amount' => null,
                'status' => 'open',
                'expires_at' => now()->addHour(),
            ]);

            $matchResult = $this->marketplaceEngine->matchOffers($offer, $counterOffer);

            if (!$matchResult->success) {
                DB::rollBack();
                return [
                    'success' => false,
                    'error' => $matchResult->error ?? 'Match failed',
                ];
            }

            $executionResult = $this->marketplaceEngine->executeMatch($offer, $counterOffer);

            if (!$executionResult->success) {
                DB::rollBack();
                return [
                    'success' => false,
                    'error' => $executionResult->error ?? 'Execution failed',
                ];
            }

            DB::commit();

            $this->auditService->log(
                'demo_offer_accepted',
                'fx_offer',
                $offer->id,
                null,
                [
                    'demo_user_id' => $demoUser->id,
                    'counter_offer_id' => $counterOffer->id,
                    'simulated' => true,
                ]
            );

            return [
                'success' => true,
                'message' => 'Demo user accepted your offer and swap executed',
                'counter_offer_id' => $counterOffer->id,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => 'Simulation failed: ' . $e->getMessage(),
            ];
        }
    }

    public function simulatePayRequest(PaymentRequest $paymentRequest): array
    {
        if (!in_array($paymentRequest->status, ['pending', 'partially_fulfilled'])) {
            return [
                'success' => false,
                'error' => 'Payment request is not pending',
            ];
        }

        $demoUser = $this->getDemoUser();
        $accounts = $this->ensureDemoUserHasAccounts();

        $currency = $paymentRequest->currency;
        if (!isset($accounts[$currency])) {
            return [
                'success' => false,
                'error' => "Demo user has no {$currency} account",
            ];
        }

        $sourceAccount = $accounts[$currency];
        $amountToPay = $paymentRequest->amount - $paymentRequest->amount_received;

        try {
            DB::beginTransaction();

            $result = $this->paymentRequestEngine->fulfillPaymentRequest(
                paymentRequest: $paymentRequest,
                payer: $demoUser,
                payerAccount: $sourceAccount,
                amount: $amountToPay
            );

            if (!$result->success) {
                DB::rollBack();
                return [
                    'success' => false,
                    'error' => $result->error ?? 'Payment failed',
                ];
            }

            DB::commit();

            $this->auditService->log(
                'demo_payment_made',
                'payment_request',
                $paymentRequest->id,
                null,
                [
                    'demo_user_id' => $demoUser->id,
                    'amount' => $amountToPay,
                    'simulated' => true,
                ]
            );

            return [
                'success' => true,
                'message' => "Demo user paid {$currency} {$amountToPay}",
                'amount_paid' => $amountToPay,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => 'Simulation failed: ' . $e->getMessage(),
            ];
        }
    }

    public function createDemoCounterOffers(string $sellCurrency, string $buyCurrency, int $count = 3): array
    {
        $demoUser = $this->getDemoUser();
        $accounts = $this->ensureDemoUserHasAccounts();

        if (!isset($accounts[$sellCurrency])) {
            return [];
        }

        $offers = [];
        $baseRates = [
            'USD/ZAR' => 18.5,
            'EUR/USD' => 1.08,
            'GBP/USD' => 1.27,
            'USD/ZWL' => 5500,
            'ZAR/USD' => 0.054,
        ];

        $pair = "{$sellCurrency}/{$buyCurrency}";
        $baseRate = $baseRates[$pair] ?? 1.0;

        for ($i = 0; $i < $count; $i++) {
            $variation = (rand(-500, 500) / 10000);
            $rate = $baseRate * (1 + $variation);

            $offer = FxOffer::create([
                'user_id' => $demoUser->id,
                'source_account_id' => $accounts[$sellCurrency]->id,
                'sell_currency' => $sellCurrency,
                'buy_currency' => $buyCurrency,
                'rate' => $rate,
                'amount' => rand(100, 5000),
                'filled_amount' => 0,
                'min_amount' => rand(10, 50),
                'status' => 'open',
                'expires_at' => now()->addHours(rand(1, 48)),
            ]);

            $offers[] = $offer;
        }

        return $offers;
    }

    public function isDemoMode(): bool
    {
        return config('app.demo_mode', false) || app()->environment('local');
    }
}

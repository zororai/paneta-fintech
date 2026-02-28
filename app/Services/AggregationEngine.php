<?php

namespace App\Services;

use App\Models\AggregatedAccount;
use App\Models\AggregatedTransaction;
use App\Models\Institution;
use App\Models\InstitutionToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AggregationEngine
{
    public function __construct(
        protected DataNormalisationEngine $normalisationEngine,
        protected TokenVaultService $tokenVault,
        protected AuditService $auditService
    ) {}

    public function getAggregatedBalance(User $user): array
    {
        $accounts = AggregatedAccount::forUser($user->id)
            ->active()
            ->with('institution')
            ->get();

        $balancesByCurrency = [];
        $totalUsd = 0;

        foreach ($accounts as $account) {
            $currency = $account->currency;
            
            if (!isset($balancesByCurrency[$currency])) {
                $balancesByCurrency[$currency] = 0;
            }
            
            $balancesByCurrency[$currency] += $account->available_balance;
        }

        return [
            'balances_by_currency' => $balancesByCurrency,
            'total_accounts' => $accounts->count(),
            'accounts' => $accounts,
            'last_refresh' => $accounts->max('last_refreshed_at'),
        ];
    }

    public function refreshUserAccounts(User $user): array
    {
        $tokens = InstitutionToken::where('user_id', $user->id)
            ->valid()
            ->with('institution')
            ->get();

        $results = [];

        foreach ($tokens as $token) {
            try {
                $result = $this->refreshInstitutionAccounts($user, $token);
                $results[$token->institution->name] = [
                    'success' => true,
                    'accounts_updated' => $result['accounts_updated'],
                    'transactions_synced' => $result['transactions_synced'],
                ];
            } catch (\Exception $e) {
                $results[$token->institution->name] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->auditService->log(
            'accounts_refreshed',
            'User',
            $user->id,
            $user,
            ['results' => $results]
        );

        return $results;
    }

    protected function refreshInstitutionAccounts(User $user, InstitutionToken $token): array
    {
        $mockAccounts = $this->fetchMockAccountsFromInstitution($token);
        $accountsUpdated = 0;
        $transactionsSynced = 0;

        DB::transaction(function () use ($user, $token, $mockAccounts, &$accountsUpdated, &$transactionsSynced) {
            foreach ($mockAccounts as $accountData) {
                $account = AggregatedAccount::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'institution_id' => $token->institution_id,
                        'external_account_id' => $accountData['external_id'],
                    ],
                    [
                        'currency' => $accountData['currency'],
                        'available_balance' => $accountData['balance'],
                        'last_refreshed_at' => now(),
                        'status' => 'active',
                    ]
                );

                $accountsUpdated++;

                foreach ($accountData['transactions'] ?? [] as $txData) {
                    $normalised = $this->normalisationEngine->normaliseTransaction($txData);
                    
                    AggregatedTransaction::updateOrCreate(
                        [
                            'aggregated_account_id' => $account->id,
                            'external_reference' => $normalised['reference'],
                        ],
                        [
                            'amount' => $normalised['amount'],
                            'currency' => $normalised['currency'],
                            'description' => $normalised['description'],
                            'transaction_type' => $normalised['type'],
                            'transaction_date' => $normalised['date'],
                        ]
                    );
                    
                    $transactionsSynced++;
                }
            }
        });

        return [
            'accounts_updated' => $accountsUpdated,
            'transactions_synced' => $transactionsSynced,
        ];
    }

    protected function fetchMockAccountsFromInstitution(InstitutionToken $token): array
    {
        $mockBalance = rand(1000, 50000) + (rand(0, 99) / 100);
        $currency = ['USD', 'EUR', 'GBP', 'ZAR'][rand(0, 3)];

        return [
            [
                'external_id' => 'ACC-' . strtoupper(substr(md5($token->id), 0, 8)),
                'currency' => $currency,
                'balance' => $mockBalance,
                'transactions' => $this->generateMockTransactions($currency),
            ],
        ];
    }

    protected function generateMockTransactions(string $currency): array
    {
        $transactions = [];
        $descriptions = [
            'Salary Payment',
            'Online Purchase',
            'Utility Bill',
            'Transfer In',
            'ATM Withdrawal',
            'Restaurant',
            'Subscription',
        ];

        for ($i = 0; $i < rand(3, 8); $i++) {
            $isCredit = rand(0, 1) === 1;
            $amount = rand(10, 500) + (rand(0, 99) / 100);
            
            $transactions[] = [
                'reference' => 'TXN-' . strtoupper(substr(md5(uniqid()), 0, 10)),
                'amount' => $isCredit ? $amount : -$amount,
                'currency' => $currency,
                'description' => $descriptions[array_rand($descriptions)],
                'type' => $isCredit ? 'credit' : 'debit',
                'date' => now()->subDays(rand(1, 30))->toISOString(),
            ];
        }

        return $transactions;
    }

    public function getConsolidatedTransactions(User $user, int $limit = 50): Collection
    {
        return AggregatedTransaction::whereHas('aggregatedAccount', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->with('aggregatedAccount.institution')
            ->orderBy('transaction_date', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getStaleAccounts(User $user): Collection
    {
        return AggregatedAccount::forUser($user->id)
            ->where(function ($query) {
                $query->where('last_refreshed_at', '<', now()->subHours(4))
                    ->orWhereNull('last_refreshed_at');
            })
            ->get();
    }
}

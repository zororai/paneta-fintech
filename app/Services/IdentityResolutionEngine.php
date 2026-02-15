<?php

namespace App\Services;

use App\Models\User;
use App\Models\LinkedAccount;
use App\Models\AggregatedTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IdentityResolutionEngine
{
    public function resolveUserIdentity(User $user): array
    {
        $accounts = $user->linkedAccounts()->with('institution')->get();
        
        $identifiers = [];
        $names = [];
        $emails = [];
        $phones = [];

        foreach ($accounts as $account) {
            if ($account->account_holder_name) {
                $names[] = $this->normalizeName($account->account_holder_name);
            }
            if ($account->account_identifier) {
                $identifiers[] = [
                    'type' => $account->institution->type ?? 'bank',
                    'identifier' => $this->maskIdentifier($account->account_identifier),
                    'institution' => $account->institution->name ?? 'Unknown',
                ];
            }
        }

        return [
            'user_id' => $user->id,
            'primary_email' => $user->email,
            'verified_name' => $this->selectBestName($names, $user->name),
            'kyc_status' => $user->kyc_status,
            'linked_identifiers' => $identifiers,
            'identity_confidence' => $this->calculateConfidenceScore($user, $accounts),
            'resolved_at' => now()->toIso8601String(),
        ];
    }

    public function matchCounterparty(string $identifier, string $name = null): ?array
    {
        // Try to find a matching user by account identifier
        $account = LinkedAccount::where('account_identifier', 'LIKE', "%{$identifier}%")->first();
        
        if ($account) {
            return [
                'matched' => true,
                'user_id' => $account->user_id,
                'confidence' => 0.95,
                'match_type' => 'account_identifier',
            ];
        }

        // Try to match by normalized name
        if ($name) {
            $normalizedName = $this->normalizeName($name);
            $users = User::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($normalizedName) . '%'])->get();
            
            if ($users->count() === 1) {
                return [
                    'matched' => true,
                    'user_id' => $users->first()->id,
                    'confidence' => 0.75,
                    'match_type' => 'name_match',
                ];
            }
        }

        return [
            'matched' => false,
            'confidence' => 0,
            'match_type' => null,
        ];
    }

    public function deduplicateTransactions(Collection $transactions): Collection
    {
        $seen = [];
        
        return $transactions->filter(function ($transaction) use (&$seen) {
            $fingerprint = $this->generateTransactionFingerprint($transaction);
            
            if (isset($seen[$fingerprint])) {
                Log::debug('Duplicate transaction filtered', [
                    'fingerprint' => $fingerprint,
                    'external_id' => $transaction['external_id'] ?? null,
                ]);
                return false;
            }
            
            $seen[$fingerprint] = true;
            return true;
        });
    }

    public function linkRelatedTransactions(User $user): array
    {
        $transactions = AggregatedTransaction::where('user_id', $user->id)
            ->orderBy('transaction_date', 'desc')
            ->limit(1000)
            ->get();

        $linked = [];
        
        foreach ($transactions as $transaction) {
            // Find potential matches (same amount, opposite type, within 24 hours)
            $potentialMatches = $transactions->filter(function ($t) use ($transaction) {
                if ($t->id === $transaction->id) return false;
                if ($t->type === $transaction->type) return false;
                if (abs($t->amount - $transaction->amount) > 0.01) return false;
                if (abs($t->transaction_date->diffInHours($transaction->transaction_date)) > 24) return false;
                return true;
            });

            if ($potentialMatches->isNotEmpty()) {
                $linked[] = [
                    'transaction_id' => $transaction->id,
                    'potential_matches' => $potentialMatches->pluck('id')->toArray(),
                    'match_reason' => 'same_amount_opposite_direction',
                ];
            }
        }

        return $linked;
    }

    public function categorizeCounterparty(string $name, string $description = null): array
    {
        $normalizedName = strtolower($name);
        
        $categories = [
            'employer' => ['salary', 'payroll', 'wages', 'employer'],
            'utility' => ['electric', 'water', 'gas', 'utility', 'power'],
            'telecom' => ['mobile', 'phone', 'telecom', 'internet', 'broadband'],
            'retail' => ['amazon', 'walmart', 'target', 'shop', 'store'],
            'transport' => ['uber', 'lyft', 'taxi', 'transport', 'fuel', 'gas station'],
            'food' => ['restaurant', 'cafe', 'coffee', 'food', 'delivery'],
            'subscription' => ['netflix', 'spotify', 'subscription', 'premium'],
            'financial' => ['bank', 'insurance', 'investment', 'loan'],
        ];

        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalizedName, $keyword) || 
                    ($description && str_contains(strtolower($description), $keyword))) {
                    return [
                        'category' => $category,
                        'confidence' => 0.8,
                    ];
                }
            }
        }

        return [
            'category' => 'other',
            'confidence' => 0.5,
        ];
    }

    public function buildUserFinancialProfile(User $user): array
    {
        $accounts = $user->linkedAccounts;
        $transactions = AggregatedTransaction::where('user_id', $user->id)
            ->where('transaction_date', '>=', now()->subMonths(3))
            ->get();

        $income = $transactions->where('type', 'credit')->sum('amount');
        $expenses = $transactions->where('type', 'debit')->sum('amount');

        $categorySpending = $transactions->where('type', 'debit')
            ->groupBy('category')
            ->map(fn($group) => $group->sum('amount'));

        return [
            'user_id' => $user->id,
            'total_accounts' => $accounts->count(),
            'total_balance' => $accounts->sum('mock_balance'),
            'primary_currency' => $accounts->groupBy('currency')->sortByDesc(fn($g) => $g->count())->keys()->first() ?? 'USD',
            'monthly_income_avg' => round($income / 3, 2),
            'monthly_expense_avg' => round($expenses / 3, 2),
            'savings_rate' => $income > 0 ? round((($income - $expenses) / $income) * 100, 2) : 0,
            'top_spending_categories' => $categorySpending->sortDesc()->take(5)->toArray(),
            'transaction_frequency' => round($transactions->count() / 3, 1),
            'profile_generated_at' => now()->toIso8601String(),
        ];
    }

    protected function normalizeName(string $name): string
    {
        // Remove titles, suffixes, extra spaces
        $name = preg_replace('/\b(Mr|Mrs|Ms|Dr|Jr|Sr|III|II|IV)\b\.?/i', '', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));
        return ucwords(strtolower($name));
    }

    protected function maskIdentifier(string $identifier): string
    {
        $length = strlen($identifier);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        return str_repeat('*', $length - 4) . substr($identifier, -4);
    }

    protected function selectBestName(array $names, string $fallback): string
    {
        if (empty($names)) {
            return $fallback;
        }

        // Return most common name or longest (most complete)
        $nameCounts = array_count_values($names);
        arsort($nameCounts);
        return array_key_first($nameCounts);
    }

    protected function calculateConfidenceScore(User $user, Collection $accounts): float
    {
        $score = 0.5; // Base score

        if ($user->kyc_status === 'verified') {
            $score += 0.3;
        }

        if ($accounts->count() >= 2) {
            $score += 0.1;
        }

        if ($user->email_verified_at) {
            $score += 0.1;
        }

        return min(1.0, $score);
    }

    protected function generateTransactionFingerprint(array $transaction): string
    {
        return md5(json_encode([
            'amount' => $transaction['amount'] ?? 0,
            'date' => substr($transaction['transaction_date'] ?? '', 0, 10),
            'type' => $transaction['type'] ?? '',
            'description' => strtolower($transaction['description'] ?? ''),
        ]));
    }
}

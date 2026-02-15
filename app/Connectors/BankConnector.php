<?php

namespace App\Connectors;

use App\Contracts\InstitutionConnectorInterface;
use App\Models\LinkedAccount;
use App\Models\User;
use App\Models\AggregatedTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BankConnector implements InstitutionConnectorInterface
{
    protected string $institutionCode;
    protected array $config;

    public function __construct(string $institutionCode, array $config = [])
    {
        $this->institutionCode = $institutionCode;
        $this->config = $config;
    }

    public function getType(): string
    {
        return 'bank';
    }

    public function initiateConnection(User $user, array $credentials): array
    {
        // In production, this would initiate OAuth with the bank's API
        Log::info('Bank connection initiated', [
            'user_id' => $user->id,
            'institution' => $this->institutionCode,
        ]);

        return [
            'auth_url' => "https://mock-bank.example.com/oauth/authorize?client_id=paneta&state=" . bin2hex(random_bytes(16)),
            'state' => bin2hex(random_bytes(16)),
            'expires_in' => 600,
        ];
    }

    public function completeConnection(User $user, string $authCode): LinkedAccount
    {
        // In production, exchange auth code for tokens
        $account = LinkedAccount::create([
            'user_id' => $user->id,
            'institution_id' => $this->getInstitutionId(),
            'account_identifier' => 'BANK-' . strtoupper(bin2hex(random_bytes(6))),
            'account_type' => 'checking',
            'currency' => 'USD',
            'consent_token' => encrypt(bin2hex(random_bytes(32))),
            'consent_expires_at' => now()->addDays(90),
            'status' => 'active',
            'mock_balance' => rand(1000, 50000) / 100 * 100,
        ]);

        Log::info('Bank account linked', [
            'user_id' => $user->id,
            'account_id' => $account->id,
        ]);

        return $account;
    }

    public function refreshConnection(LinkedAccount $account): bool
    {
        // Refresh OAuth tokens
        $account->update([
            'consent_expires_at' => now()->addDays(90),
            'last_synced_at' => now(),
        ]);

        return true;
    }

    public function revokeConnection(LinkedAccount $account): bool
    {
        $account->update([
            'status' => 'revoked',
            'consent_token' => null,
        ]);

        return true;
    }

    public function fetchAccounts(LinkedAccount $account): Collection
    {
        // In production, fetch from bank API
        return collect([
            [
                'account_id' => $account->account_identifier,
                'type' => $account->account_type,
                'currency' => $account->currency,
                'balance' => $account->mock_balance,
            ],
        ]);
    }

    public function fetchTransactions(LinkedAccount $account, \DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        // In production, fetch from bank API
        // For MVP, generate mock transactions
        $transactions = collect();
        
        $categories = ['shopping', 'groceries', 'utilities', 'transport', 'salary', 'transfer_in'];
        $merchants = ['Amazon', 'Walmart', 'Uber', 'Netflix', 'Employer Inc', 'John Doe'];

        for ($i = 0; $i < rand(5, 15); $i++) {
            $isCredit = rand(0, 1) === 1;
            $transactions->push([
                'external_id' => 'TXN-' . bin2hex(random_bytes(8)),
                'type' => $isCredit ? 'credit' : 'debit',
                'amount' => rand(10, 500) + (rand(0, 99) / 100),
                'currency' => $account->currency,
                'description' => $merchants[array_rand($merchants)] . ' - ' . ($isCredit ? 'Payment' : 'Purchase'),
                'category' => $categories[array_rand($categories)],
                'transaction_date' => $from->modify('+' . rand(0, 30) . ' days')->format('Y-m-d H:i:s'),
                'status' => 'posted',
            ]);
        }

        return $transactions;
    }

    public function fetchBalance(LinkedAccount $account): array
    {
        return [
            'available' => $account->mock_balance,
            'current' => $account->mock_balance,
            'currency' => $account->currency,
            'as_of' => now()->toIso8601String(),
        ];
    }

    public function validateCredentials(array $credentials): bool
    {
        return !empty($credentials['auth_code']);
    }

    public function isAvailable(): bool
    {
        // Check if bank API is available
        return true;
    }

    public function getHealthStatus(): array
    {
        return [
            'status' => 'healthy',
            'latency_ms' => rand(50, 200),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    protected function getInstitutionId(): int
    {
        return \App\Models\Institution::where('code', $this->institutionCode)->first()?->id ?? 1;
    }
}

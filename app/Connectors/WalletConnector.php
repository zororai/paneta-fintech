<?php

namespace App\Connectors;

use App\Contracts\InstitutionConnectorInterface;
use App\Models\LinkedAccount;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WalletConnector implements InstitutionConnectorInterface
{
    protected string $walletProvider;
    protected array $config;

    public function __construct(string $walletProvider, array $config = [])
    {
        $this->walletProvider = $walletProvider;
        $this->config = $config;
    }

    public function getType(): string
    {
        return 'wallet';
    }

    public function initiateConnection(User $user, array $credentials): array
    {
        Log::info('Wallet connection initiated', [
            'user_id' => $user->id,
            'provider' => $this->walletProvider,
        ]);

        return [
            'auth_url' => "https://mock-wallet.example.com/connect?provider={$this->walletProvider}&state=" . bin2hex(random_bytes(16)),
            'state' => bin2hex(random_bytes(16)),
            'expires_in' => 300,
        ];
    }

    public function completeConnection(User $user, string $authCode): LinkedAccount
    {
        $account = LinkedAccount::create([
            'user_id' => $user->id,
            'institution_id' => $this->getInstitutionId(),
            'account_identifier' => 'WAL-' . strtoupper(bin2hex(random_bytes(6))),
            'account_type' => 'wallet',
            'currency' => 'USD',
            'consent_token' => encrypt(bin2hex(random_bytes(32))),
            'consent_expires_at' => now()->addYear(),
            'status' => 'active',
            'mock_balance' => rand(100, 5000) / 100 * 100,
        ]);

        Log::info('Wallet linked', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'provider' => $this->walletProvider,
        ]);

        return $account;
    }

    public function refreshConnection(LinkedAccount $account): bool
    {
        $account->update([
            'consent_expires_at' => now()->addYear(),
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
        return collect([
            [
                'account_id' => $account->account_identifier,
                'type' => 'wallet',
                'currency' => $account->currency,
                'balance' => $account->mock_balance,
            ],
        ]);
    }

    public function fetchTransactions(LinkedAccount $account, \DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        $transactions = collect();
        
        $types = ['p2p_received', 'p2p_sent', 'top_up', 'withdrawal', 'payment'];

        for ($i = 0; $i < rand(3, 10); $i++) {
            $type = $types[array_rand($types)];
            $isCredit = in_array($type, ['p2p_received', 'top_up']);
            
            $transactions->push([
                'external_id' => 'WTXN-' . bin2hex(random_bytes(8)),
                'type' => $isCredit ? 'credit' : 'debit',
                'amount' => rand(5, 200) + (rand(0, 99) / 100),
                'currency' => $account->currency,
                'description' => ucfirst(str_replace('_', ' ', $type)),
                'category' => $isCredit ? 'transfer_in' : 'transfer_out',
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
            'pending' => 0,
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
        return true;
    }

    public function getHealthStatus(): array
    {
        return [
            'status' => 'healthy',
            'latency_ms' => rand(30, 100),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    protected function getInstitutionId(): int
    {
        return \App\Models\Institution::where('code', $this->walletProvider)->first()?->id ?? 1;
    }
}

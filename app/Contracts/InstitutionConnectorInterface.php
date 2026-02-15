<?php

namespace App\Contracts;

use App\Models\LinkedAccount;
use App\Models\User;
use Illuminate\Support\Collection;

interface InstitutionConnectorInterface
{
    public function getType(): string;

    public function initiateConnection(User $user, array $credentials): array;

    public function completeConnection(User $user, string $authCode): LinkedAccount;

    public function refreshConnection(LinkedAccount $account): bool;

    public function revokeConnection(LinkedAccount $account): bool;

    public function fetchAccounts(LinkedAccount $account): Collection;

    public function fetchTransactions(LinkedAccount $account, \DateTimeInterface $from, \DateTimeInterface $to): Collection;

    public function fetchBalance(LinkedAccount $account): array;

    public function validateCredentials(array $credentials): bool;

    public function isAvailable(): bool;

    public function getHealthStatus(): array;
}

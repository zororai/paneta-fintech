<?php

namespace App\Services;

use App\Models\LinkedAccount;
use App\Models\User;

class ComplianceEngine
{
    private const DAILY_LIMIT_LOW = 10000.00;
    private const DAILY_LIMIT_MEDIUM = 50000.00;
    private const DAILY_LIMIT_HIGH = 100000.00;

    public function checkTransaction(User $user, LinkedAccount $account, float $amount): ComplianceResult
    {
        $checks = [];

        // Check KYC status
        $kycCheck = $this->checkKyc($user);
        $checks[] = $kycCheck;
        if (!$kycCheck['passed']) {
            return new ComplianceResult(false, $checks, 'KYC verification required');
        }

        // Check amount is positive
        $amountCheck = $this->checkAmount($amount);
        $checks[] = $amountCheck;
        if (!$amountCheck['passed']) {
            return new ComplianceResult(false, $checks, 'Invalid amount');
        }

        // Check account is active
        $accountCheck = $this->checkAccountStatus($account);
        $checks[] = $accountCheck;
        if (!$accountCheck['passed']) {
            return new ComplianceResult(false, $checks, 'Account not active or consent expired');
        }

        // Check daily limit
        $limitCheck = $this->checkDailyLimit($user, $amount);
        $checks[] = $limitCheck;
        if (!$limitCheck['passed']) {
            return new ComplianceResult(false, $checks, 'Daily limit exceeded');
        }

        // Check sufficient balance
        $balanceCheck = $this->checkSufficientBalance($account, $amount);
        $checks[] = $balanceCheck;
        if (!$balanceCheck['passed']) {
            return new ComplianceResult(false, $checks, 'Insufficient balance');
        }

        return new ComplianceResult(true, $checks);
    }

    private function checkKyc(User $user): array
    {
        return [
            'check' => 'kyc_verified',
            'passed' => $user->isKycVerified(),
            'details' => ['status' => $user->kyc_status],
        ];
    }

    private function checkAmount(float $amount): array
    {
        return [
            'check' => 'amount_positive',
            'passed' => $amount > 0,
            'details' => ['amount' => $amount],
        ];
    }

    private function checkAccountStatus(LinkedAccount $account): array
    {
        return [
            'check' => 'account_active',
            'passed' => $account->isConsentValid(),
            'details' => [
                'status' => $account->status,
                'consent_expires_at' => $account->consent_expires_at?->toIso8601String(),
            ],
        ];
    }

    private function checkDailyLimit(User $user, float $amount): array
    {
        $limit = $this->getDailyLimit($user);
        $todayTotal = $this->getTodayTotal($user);
        $wouldExceed = ($todayTotal + $amount) > $limit;

        return [
            'check' => 'daily_limit',
            'passed' => !$wouldExceed,
            'details' => [
                'limit' => $limit,
                'today_total' => $todayTotal,
                'requested' => $amount,
                'risk_tier' => $user->risk_tier,
            ],
        ];
    }

    private function checkSufficientBalance(LinkedAccount $account, float $amount): array
    {
        return [
            'check' => 'sufficient_balance',
            'passed' => $account->hasSufficientBalance($amount),
            'details' => [
                'balance' => $account->mock_balance,
                'requested' => $amount,
            ],
        ];
    }

    private function getDailyLimit(User $user): float
    {
        return match ($user->risk_tier) {
            'low' => self::DAILY_LIMIT_LOW,
            'medium' => self::DAILY_LIMIT_MEDIUM,
            'high' => self::DAILY_LIMIT_HIGH,
            default => self::DAILY_LIMIT_LOW,
        };
    }

    private function getTodayTotal(User $user): float
    {
        return $user->transactionIntents()
            ->whereDate('created_at', today())
            ->whereIn('status', ['confirmed', 'executed'])
            ->sum('amount');
    }
}

class ComplianceResult
{
    public function __construct(
        public readonly bool $passed,
        public readonly array $checks,
        public readonly ?string $failureReason = null
    ) {}

    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'checks' => $this->checks,
            'failure_reason' => $this->failureReason,
        ];
    }
}

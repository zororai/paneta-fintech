<?php

namespace App\Services;

use App\Models\LinkedAccount;
use App\Models\PaymentInstruction;

class MockInstitutionService
{
    public function simulatePaymentExecution(PaymentInstruction $instruction): MockExecutionResult
    {
        // Simulate network latency
        usleep(random_int(100000, 500000)); // 100-500ms

        // Simulate success rate (95% success for demo)
        $isSuccess = random_int(1, 100) <= 95;

        if (!$isSuccess) {
            return new MockExecutionResult(
                success: false,
                externalReference: null,
                errorCode: 'MOCK_FAILURE',
                errorMessage: 'Simulated institution rejection'
            );
        }

        // Generate mock external reference
        $externalReference = 'EXT-' . strtoupper(bin2hex(random_bytes(8)));

        return new MockExecutionResult(
            success: true,
            externalReference: $externalReference,
            errorCode: null,
            errorMessage: null
        );
    }

    public function simulateBalanceDeduction(LinkedAccount $account, float $amount): bool
    {
        if ($account->mock_balance < $amount) {
            return false;
        }

        $account->decrement('mock_balance', $amount);
        return true;
    }

    public function simulateBalanceCredit(LinkedAccount $account, float $amount): bool
    {
        $account->increment('mock_balance', $amount);
        return true;
    }

    public function fetchMockBalance(LinkedAccount $account): float
    {
        // In a real system, this would call the institution's API
        // For MVP, just return the stored mock balance with slight variation
        $variation = random_int(-100, 100) / 100;
        return max(0, $account->mock_balance + $variation);
    }

    public function validateAccountIdentifier(string $identifier): bool
    {
        // Mock validation - check format
        return preg_match('/^(ACC|WAL|FXA)-[A-Z0-9]{8}-\d{4}$/', $identifier) === 1;
    }
}

class MockExecutionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalReference,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'external_reference' => $this->externalReference,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\MerchantDevice;
use App\Models\User;
use App\Models\LinkedAccount;
use Illuminate\Support\Str;

class MerchantOrchestrationEngine
{
    public function __construct(
        protected AuditService $auditService,
        protected FeeEngine $feeEngine,
        protected PaymentRequestEngine $paymentRequestEngine
    ) {}

    public function registerMerchant(
        User $user,
        string $businessName,
        ?string $businessRegistrationNumber = null,
        ?string $businessType = null,
        ?string $businessSector = null,
        string $country = 'ZA',
        ?string $taxId = null,
        ?string $businessLogo = null,
        ?string $reportingCurrency = null,
        ?int $settlementAccountId = null,
        ?array $otherSettlementAccounts = null
    ): Merchant {
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'business_name' => $businessName,
            'business_registration_number' => $businessRegistrationNumber,
            'business_type' => $businessType,
            'business_sector' => $businessSector,
            'country' => $country,
            'tax_id' => $taxId,
            'business_logo' => $businessLogo,
            'reporting_currency' => $reportingCurrency,
            'settlement_account_id' => $settlementAccountId,
            'other_settlement_accounts' => $otherSettlementAccounts,
            'kyb_status' => 'pending',
            'is_active' => false,
        ]);

        $this->auditService->log(
            $user->id,
            'merchant_registered',
            'Merchant',
            $merchant->id,
            ['business_name' => $businessName]
        );

        return $merchant;
    }

    public function verifyMerchant(Merchant $merchant): Merchant
    {
        $merchant->update([
            'kyb_status' => 'verified',
            'is_active' => true,
        ]);

        $this->auditService->log(
            $merchant->user_id,
            'merchant_verified',
            'Merchant',
            $merchant->id,
            []
        );

        return $merchant->fresh();
    }

    public function setSettlementAccount(Merchant $merchant, LinkedAccount $account): Merchant
    {
        if ($account->user_id !== $merchant->user_id) {
            throw new \InvalidArgumentException('Settlement account must belong to merchant owner');
        }

        $merchant->update([
            'settlement_account_id' => $account->id,
            'default_currency' => $account->currency,
        ]);

        return $merchant->fresh();
    }

    public function registerDevice(
        Merchant $merchant,
        ?string $deviceName = null,
        ?string $deviceType = null
    ): MerchantDevice {
        $device = MerchantDevice::create([
            'merchant_id' => $merchant->id,
            'device_identifier' => MerchantDevice::generateDeviceIdentifier(),
            'device_name' => $deviceName ?? 'Device ' . ($merchant->devices()->count() + 1),
            'device_type' => $deviceType ?? 'terminal',
            'status' => 'active',
        ]);

        $this->auditService->log(
            $merchant->user_id,
            'merchant_device_registered',
            'MerchantDevice',
            $device->id,
            ['merchant_id' => $merchant->id]
        );

        return $device;
    }

    public function deactivateDevice(MerchantDevice $device): MerchantDevice
    {
        $device->update(['status' => 'inactive']);
        return $device->fresh();
    }

    public function generatePaymentQr(
        Merchant $merchant,
        MerchantDevice $device,
        float $amount,
        ?string $description = null,
        int $expiresInMinutes = 15
    ): array {
        if (!$merchant->canAcceptPayments()) {
            throw new \Exception('Merchant cannot accept payments');
        }

        if (!$device->isActive()) {
            throw new \Exception('Device is not active');
        }

        $device->recordActivity(request()->ip());

        $paymentRequest = $this->paymentRequestEngine->createPaymentRequest(
            user: $merchant->user,
            amount: $amount,
            currency: $merchant->default_currency,
            linkedAccount: $merchant->settlementAccount,
            description: $description ?? "Payment to {$merchant->business_name}",
            allowPartial: false,
            expiresInMinutes: $expiresInMinutes
        );

        return [
            'payment_request_id' => $paymentRequest->id,
            'reference' => $paymentRequest->reference,
            'qr_code_data' => $paymentRequest->qr_code_data,
            'amount' => $amount,
            'currency' => $merchant->default_currency,
            'merchant_name' => $merchant->business_name,
            'expires_at' => $paymentRequest->expires_at,
        ];
    }

    public function processPayment(
        Merchant $merchant,
        User $customer,
        LinkedAccount $customerAccount,
        string $paymentReference
    ): MerchantPaymentResult {
        $paymentRequest = $this->paymentRequestEngine->findByReference($paymentReference);

        if (!$paymentRequest) {
            return new MerchantPaymentResult(
                success: false,
                error: 'Payment request not found'
            );
        }

        if ($paymentRequest->user_id !== $merchant->user_id) {
            return new MerchantPaymentResult(
                success: false,
                error: 'Payment request does not belong to this merchant'
            );
        }

        $grossAmount = $paymentRequest->amount;
        $fee = $merchant->calculateFee($grossAmount);
        $netAmount = $grossAmount - $fee;

        $fulfillmentResult = $this->paymentRequestEngine->fulfillPaymentRequest(
            $paymentRequest,
            $customer,
            $customerAccount
        );

        if (!$fulfillmentResult->success) {
            return new MerchantPaymentResult(
                success: false,
                error: $fulfillmentResult->error
            );
        }

        $this->feeEngine->recordFee(
            $customer,
            'merchant_payment',
            $paymentRequest->id,
            $fee,
            $merchant->default_currency,
            'merchant'
        );

        $this->auditService->log(
            $customer->id,
            'merchant_payment_completed',
            'PaymentRequest',
            $paymentRequest->id,
            [
                'merchant_id' => $merchant->id,
                'gross_amount' => $grossAmount,
                'fee' => $fee,
                'net_amount' => $netAmount,
            ]
        );

        return new MerchantPaymentResult(
            success: true,
            transactionReference: $paymentRequest->reference,
            grossAmount: $grossAmount,
            fee: $fee,
            netAmount: $netAmount,
            currency: $merchant->default_currency
        );
    }

    public function getMerchantStats(Merchant $merchant): array
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        return [
            'total_transactions' => $merchant->user->transactionIntents()->count(),
            'transactions_today' => $merchant->user->transactionIntents()
                ->where('created_at', '>=', $today)
                ->count(),
            'transactions_this_month' => $merchant->user->transactionIntents()
                ->where('created_at', '>=', $thisMonth)
                ->count(),
            'active_devices' => $merchant->devices()->active()->count(),
            'kyb_status' => $merchant->kyb_status,
            'is_active' => $merchant->is_active,
        ];
    }
}

class MerchantPaymentResult
{
    public function __construct(
        public bool $success,
        public ?string $error = null,
        public ?string $transactionReference = null,
        public float $grossAmount = 0,
        public float $fee = 0,
        public float $netAmount = 0,
        public ?string $currency = null
    ) {}
}

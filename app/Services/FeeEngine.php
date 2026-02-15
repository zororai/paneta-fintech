<?php

namespace App\Services;

use App\Models\FeeLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FeeEngine
{
    const DEFAULT_FEE_PERCENTAGE = 0.99;
    const CROSS_BORDER_FEE_PERCENTAGE = 1.49;
    const P2P_FX_FEE_PERCENTAGE = 0.50;
    const MERCHANT_FEE_PERCENTAGE = 2.50;

    public function calculateFee(float $amount, string $feeType = 'platform'): float
    {
        $percentage = $this->getFeePercentage($feeType);
        return round($amount * ($percentage / 100), 2);
    }

    public function getFeePercentage(string $feeType): float
    {
        return match ($feeType) {
            'cross_border' => self::CROSS_BORDER_FEE_PERCENTAGE,
            'p2p_fx' => self::P2P_FX_FEE_PERCENTAGE,
            'merchant' => self::MERCHANT_FEE_PERCENTAGE,
            default => self::DEFAULT_FEE_PERCENTAGE,
        };
    }

    public function recordFee(
        ?User $user,
        string $transactionType,
        int $transactionId,
        float $amount,
        string $currency,
        string $feeType = 'platform'
    ): FeeLedger {
        return FeeLedger::create([
            'user_id' => $user?->id,
            'transaction_type' => $transactionType,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
            'fee_percentage' => $this->getFeePercentage($feeType),
            'fee_type' => $feeType,
            'status' => 'collected',
        ]);
    }

    public function calculateAndRecordFee(
        ?User $user,
        string $transactionType,
        int $transactionId,
        float $transactionAmount,
        string $currency,
        string $feeType = 'platform'
    ): FeeLedger {
        $feeAmount = $this->calculateFee($transactionAmount, $feeType);

        return $this->recordFee(
            $user,
            $transactionType,
            $transactionId,
            $feeAmount,
            $currency,
            $feeType
        );
    }

    public function refundFee(FeeLedger $feeLedger): FeeLedger
    {
        $feeLedger->update(['status' => 'refunded']);
        return $feeLedger;
    }

    public function getTotalRevenue(
        ?string $currency = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): float {
        $query = FeeLedger::collected();

        if ($currency) {
            $query->forCurrency($currency);
        }

        if ($startDate && $endDate) {
            $query->inPeriod($startDate, $endDate);
        }

        return $query->sum('amount');
    }

    public function getRevenueByType(
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $query = FeeLedger::collected();

        if ($startDate && $endDate) {
            $query->inPeriod($startDate, $endDate);
        }

        return $query->groupBy('fee_type')
            ->select('fee_type', DB::raw('SUM(amount) as total'))
            ->pluck('total', 'fee_type')
            ->toArray();
    }

    public function getRevenueByCurrency(
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $query = FeeLedger::collected();

        if ($startDate && $endDate) {
            $query->inPeriod($startDate, $endDate);
        }

        return $query->groupBy('currency')
            ->select('currency', DB::raw('SUM(amount) as total'))
            ->pluck('total', 'currency')
            ->toArray();
    }

    public function getUserTotalFees(User $user): float
    {
        return FeeLedger::where('user_id', $user->id)
            ->collected()
            ->sum('amount');
    }
}

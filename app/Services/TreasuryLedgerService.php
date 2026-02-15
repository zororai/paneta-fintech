<?php

namespace App\Services;

use App\Models\CurrencyBalance;
use App\Models\FeeLedger;
use App\Models\PlatformLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TreasuryLedgerService
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function recordFeeCollection(
        float $amount,
        string $currency,
        string $referenceType,
        int $referenceId,
        ?User $user = null,
        ?string $description = null
    ): PlatformLedger {
        return DB::transaction(function () use ($amount, $currency, $referenceType, $referenceId, $user, $description) {
            $entry = PlatformLedger::create([
                'entry_type' => PlatformLedger::TYPE_FEE,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'description' => $description ?? "Fee collected for {$referenceType}",
                'metadata' => [
                    'user_id' => $user?->id,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            CurrencyBalance::forCurrency($currency)->addFee($amount);

            return $entry;
        });
    }

    public function recordRefund(
        float $amount,
        string $currency,
        string $referenceType,
        int $referenceId,
        ?User $user = null,
        ?string $description = null
    ): PlatformLedger {
        return DB::transaction(function () use ($amount, $currency, $referenceType, $referenceId, $user, $description) {
            $entry = PlatformLedger::create([
                'entry_type' => PlatformLedger::TYPE_REFUND,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'description' => $description ?? "Refund for {$referenceType}",
                'metadata' => [
                    'user_id' => $user?->id,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            CurrencyBalance::forCurrency($currency)->addRefund($amount);

            return $entry;
        });
    }

    public function recordAdjustment(
        float $amount,
        string $currency,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?User $adjustedBy = null
    ): PlatformLedger {
        return DB::transaction(function () use ($amount, $currency, $description, $referenceType, $referenceId, $adjustedBy) {
            $entry = PlatformLedger::create([
                'entry_type' => PlatformLedger::TYPE_ADJUSTMENT,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'description' => $description,
                'metadata' => [
                    'adjusted_by' => $adjustedBy?->id,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            CurrencyBalance::forCurrency($currency)->addAdjustment($amount);

            if ($adjustedBy) {
                $this->auditService->log(
                    $adjustedBy->id,
                    'ledger_adjustment',
                    'PlatformLedger',
                    $entry->id,
                    [
                        'amount' => $amount,
                        'currency' => $currency,
                        'description' => $description,
                    ]
                );
            }

            return $entry;
        });
    }

    public function recordWriteOff(
        float $amount,
        string $currency,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        User $approvedBy
    ): PlatformLedger {
        return DB::transaction(function () use ($amount, $currency, $description, $referenceType, $referenceId, $approvedBy) {
            $entry = PlatformLedger::create([
                'entry_type' => PlatformLedger::TYPE_WRITE_OFF,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'description' => $description,
                'metadata' => [
                    'approved_by' => $approvedBy->id,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            CurrencyBalance::forCurrency($currency)->addRefund($amount);

            $this->auditService->log(
                $approvedBy->id,
                'ledger_write_off',
                'PlatformLedger',
                $entry->id,
                [
                    'amount' => $amount,
                    'currency' => $currency,
                    'description' => $description,
                ]
            );

            return $entry;
        });
    }

    public function getCurrencyBalances(): array
    {
        return CurrencyBalance::all()
            ->keyBy('currency')
            ->toArray();
    }

    public function getCurrencyBalance(string $currency): array
    {
        $balance = CurrencyBalance::forCurrency($currency);

        return [
            'currency' => $balance->currency,
            'total_fees_collected' => (float) $balance->total_fees_collected,
            'total_refunded' => (float) $balance->total_refunded,
            'total_adjustments' => (float) $balance->total_adjustments,
            'net_position' => (float) $balance->net_position,
            'updated_at' => $balance->updated_at?->toIso8601String(),
        ];
    }

    public function getTotalNetPosition(): array
    {
        $balances = CurrencyBalance::all();

        $result = [];
        foreach ($balances as $balance) {
            $result[$balance->currency] = (float) $balance->net_position;
        }

        return $result;
    }

    public function getLedgerEntries(
        ?string $currency = null,
        ?string $entryType = null,
        ?string $startDate = null,
        ?string $endDate = null,
        int $limit = 100
    ): array {
        $query = PlatformLedger::query()
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($currency) {
            $query->where('currency', strtoupper($currency));
        }

        if ($entryType) {
            $query->where('entry_type', $entryType);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        return $query->get()->toArray();
    }

    public function getDailyRevenue(string $currency, int $days = 30): array
    {
        return PlatformLedger::where('currency', strtoupper($currency))
            ->where('entry_type', PlatformLedger::TYPE_FEE)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    public function getRevenueByType(string $currency, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = PlatformLedger::where('currency', strtoupper($currency))
            ->where('entry_type', PlatformLedger::TYPE_FEE);

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        return $query->selectRaw('reference_type, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('reference_type')
            ->get()
            ->toArray();
    }

    public function reconcileLedgerWithFeeLedger(): array
    {
        $feeLedgerTotals = FeeLedger::where('status', 'collected')
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->toArray();

        $platformLedgerTotals = PlatformLedger::where('entry_type', PlatformLedger::TYPE_FEE)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->toArray();

        $discrepancies = [];
        $allCurrencies = array_unique(array_merge(
            array_keys($feeLedgerTotals),
            array_keys($platformLedgerTotals)
        ));

        foreach ($allCurrencies as $currency) {
            $feeLedgerAmount = $feeLedgerTotals[$currency] ?? 0;
            $platformLedgerAmount = $platformLedgerTotals[$currency] ?? 0;
            $difference = abs($feeLedgerAmount - $platformLedgerAmount);

            if ($difference > 0.01) {
                $discrepancies[] = [
                    'currency' => $currency,
                    'fee_ledger_total' => $feeLedgerAmount,
                    'platform_ledger_total' => $platformLedgerAmount,
                    'difference' => $difference,
                ];
            }
        }

        return [
            'reconciled' => empty($discrepancies),
            'discrepancies' => $discrepancies,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}

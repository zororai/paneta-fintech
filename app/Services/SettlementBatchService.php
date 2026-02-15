<?php

namespace App\Services;

use App\Models\SettlementBatch;
use App\Models\SettlementBatchItem;
use App\Models\TransactionIntent;
use App\Models\Merchant;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettlementBatchService
{
    protected AuditService $audit;

    public function __construct(AuditService $audit)
    {
        $this->audit = $audit;
    }

    public function createMerchantPayoutBatch(
        string $currency,
        \DateTimeInterface $scheduledAt = null
    ): SettlementBatch {
        return DB::transaction(function () use ($currency, $scheduledAt) {
            // Find all unsettled merchant transactions
            $transactions = TransactionIntent::where('status', 'executed')
                ->where('currency', $currency)
                ->whereNull('settlement_batch_id')
                ->whereNotNull('merchant_id')
                ->get();

            if ($transactions->isEmpty()) {
                throw new \RuntimeException('No transactions available for settlement');
            }

            $batch = SettlementBatch::create([
                'batch_reference' => SettlementBatch::generateReference(),
                'status' => SettlementBatch::STATUS_PENDING,
                'batch_type' => SettlementBatch::TYPE_MERCHANT_PAYOUT,
                'currency' => $currency,
                'scheduled_at' => $scheduledAt ?? now()->addHours(24),
            ]);

            foreach ($transactions as $transaction) {
                $fee = $transaction->platform_fee ?? 0;
                $netAmount = $transaction->amount - $fee;

                SettlementBatchItem::create([
                    'settlement_batch_id' => $batch->id,
                    'settleable_type' => TransactionIntent::class,
                    'settleable_id' => $transaction->id,
                    'merchant_id' => $transaction->merchant_id,
                    'amount' => $transaction->amount,
                    'fee' => $fee,
                    'net_amount' => $netAmount,
                    'currency' => $currency,
                    'status' => SettlementBatchItem::STATUS_PENDING,
                ]);

                $transaction->update(['settlement_batch_id' => $batch->id]);
            }

            $batch->recalculateTotals();

            Log::info('Settlement batch created', [
                'batch_id' => $batch->id,
                'type' => $batch->batch_type,
                'transaction_count' => $batch->transaction_count,
                'total_amount' => $batch->total_amount,
            ]);

            return $batch;
        });
    }

    public function createRefundBatch(array $transactionIds, string $currency): SettlementBatch
    {
        return DB::transaction(function () use ($transactionIds, $currency) {
            $batch = SettlementBatch::create([
                'batch_reference' => SettlementBatch::generateReference(),
                'status' => SettlementBatch::STATUS_PENDING,
                'batch_type' => SettlementBatch::TYPE_REFUND_BATCH,
                'currency' => $currency,
                'scheduled_at' => now(),
            ]);

            foreach ($transactionIds as $transactionId) {
                $transaction = TransactionIntent::findOrFail($transactionId);
                
                SettlementBatchItem::create([
                    'settlement_batch_id' => $batch->id,
                    'settleable_type' => TransactionIntent::class,
                    'settleable_id' => $transaction->id,
                    'recipient_id' => $transaction->user_id,
                    'amount' => $transaction->amount,
                    'fee' => 0,
                    'net_amount' => $transaction->amount,
                    'currency' => $currency,
                    'status' => SettlementBatchItem::STATUS_PENDING,
                ]);
            }

            $batch->recalculateTotals();

            return $batch;
        });
    }

    public function processBatch(SettlementBatch $batch, User $processor): SettlementBatch
    {
        if (!$batch->isPending()) {
            throw new \RuntimeException('Batch is not in pending status');
        }

        return DB::transaction(function () use ($batch, $processor) {
            $batch->update(['processed_by' => $processor->id]);
            $batch->startProcessing();

            foreach ($batch->items as $item) {
                try {
                    $this->processItem($item);
                    $item->markCompleted($this->generateSettlementReference());
                } catch (\Exception $e) {
                    Log::error('Settlement item failed', [
                        'item_id' => $item->id,
                        'error' => $e->getMessage(),
                    ]);
                    $item->markFailed($e->getMessage());
                }
            }

            $batch->recalculateTotals();
            $batch->markCompleted();

            $this->audit->log(
                $processor->id,
                'settlement_batch_processed',
                'settlement_batch',
                $batch->id,
                [
                    'successful_count' => $batch->successful_count,
                    'failed_count' => $batch->failed_count,
                    'total_amount' => $batch->total_amount,
                ]
            );

            Log::info('Settlement batch processed', [
                'batch_id' => $batch->id,
                'status' => $batch->status,
                'successful' => $batch->successful_count,
                'failed' => $batch->failed_count,
            ]);

            return $batch->fresh();
        });
    }

    protected function processItem(SettlementBatchItem $item): void
    {
        // In production, this would initiate actual bank transfers
        // For MVP, simulate processing with high success rate
        if (rand(1, 100) > 98) {
            throw new \RuntimeException('Simulated settlement failure');
        }

        // Mark the underlying transaction as settled
        if ($item->settleable) {
            $item->settleable->update(['settled_at' => now()]);
        }
    }

    protected function generateSettlementReference(): string
    {
        return 'STL-' . strtoupper(bin2hex(random_bytes(8)));
    }

    public function processScheduledBatches(): array
    {
        $batches = SettlementBatch::scheduledForProcessing()->get();
        $results = [];

        foreach ($batches as $batch) {
            try {
                $adminUser = User::where('role', 'admin')->first();
                $this->processBatch($batch, $adminUser);
                $results[] = [
                    'batch_id' => $batch->id,
                    'status' => 'processed',
                ];
            } catch (\Exception $e) {
                Log::error('Batch processing failed', [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);
                $results[] = [
                    'batch_id' => $batch->id,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function getMerchantSettlementSummary(Merchant $merchant, int $days = 30): array
    {
        $since = now()->subDays($days);

        $items = SettlementBatchItem::where('merchant_id', $merchant->id)
            ->where('created_at', '>=', $since)
            ->with('batch')
            ->get();

        return [
            'merchant_id' => $merchant->id,
            'period_days' => $days,
            'total_transactions' => $items->count(),
            'total_gross' => $items->sum('amount'),
            'total_fees' => $items->sum('fee'),
            'total_net' => $items->sum('net_amount'),
            'pending' => $items->where('status', 'pending')->sum('net_amount'),
            'completed' => $items->where('status', 'completed')->sum('net_amount'),
            'failed' => $items->where('status', 'failed')->sum('net_amount'),
            'by_batch' => $items->groupBy('settlement_batch_id')->map(function ($batchItems) {
                return [
                    'batch_reference' => $batchItems->first()->batch->batch_reference ?? 'N/A',
                    'count' => $batchItems->count(),
                    'net_amount' => $batchItems->sum('net_amount'),
                ];
            })->values(),
        ];
    }

    public function getSettlementStats(int $days = 30): array
    {
        $since = now()->subDays($days);

        $batches = SettlementBatch::where('created_at', '>=', $since)->get();

        return [
            'period_days' => $days,
            'total_batches' => $batches->count(),
            'total_amount' => $batches->sum('total_amount'),
            'total_fees' => $batches->sum('total_fees'),
            'total_net' => $batches->sum('net_amount'),
            'by_status' => $batches->groupBy('status')->map->count(),
            'by_type' => $batches->groupBy('batch_type')->map->count(),
            'success_rate' => $batches->count() > 0 
                ? round(($batches->where('status', 'completed')->count() / $batches->count()) * 100, 2) 
                : 0,
            'avg_batch_size' => round($batches->avg('transaction_count'), 1),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * ImmutableAuditSealService
 * 
 * Provides hash-chained audit records for tamper-proof logging:
 * - Hash-chained audit records
 * - Tamper-proof log sealing
 * - Log integrity verification
 * - Blockchain-style immutability
 */
class ImmutableAuditSealService
{
    private const BLOCK_SIZE = 100;
    private const CACHE_KEY_LAST_HASH = 'audit_chain_last_hash';

    public function __construct(
        private readonly KeyManagementService $keyManagementService
    ) {}

    /**
     * Generate hash linking to previous record
     */
    public function generateHash(string $previousHash, array $record): string
    {
        $data = json_encode([
            'previous_hash' => $previousHash,
            'record' => $record,
            'timestamp' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES);

        return hash('sha256', $data);
    }

    /**
     * Seal a block of audit records
     */
    public function sealAuditBlock(): array
    {
        return DB::transaction(function () {
            $unsealedLogs = AuditLog::whereNull('block_hash')
                ->orderBy('id')
                ->limit(self::BLOCK_SIZE)
                ->lockForUpdate()
                ->get();

            if ($unsealedLogs->isEmpty()) {
                return ['sealed' => 0, 'block_hash' => null];
            }

            $previousHash = $this->getLastBlockHash();
            $blockData = $this->buildBlockData($unsealedLogs);
            $blockHash = $this->generateHash($previousHash, $blockData);

            $unsealedLogs->each(function (AuditLog $log) use ($blockHash) {
                $log->update([
                    'block_hash' => $blockHash,
                    'sealed_at' => now(),
                ]);
            });

            $this->storeBlockHash($blockHash);

            return [
                'sealed' => $unsealedLogs->count(),
                'block_hash' => $blockHash,
                'previous_hash' => $previousHash,
                'first_id' => $unsealedLogs->first()->id,
                'last_id' => $unsealedLogs->last()->id,
            ];
        });
    }

    /**
     * Verify integrity of the entire audit chain
     */
    public function verifyAuditChainIntegrity(): array
    {
        $blocks = AuditLog::whereNotNull('block_hash')
            ->select('block_hash')
            ->distinct()
            ->orderBy('id')
            ->pluck('block_hash');

        if ($blocks->isEmpty()) {
            return ['valid' => true, 'blocks_verified' => 0, 'errors' => []];
        }

        $errors = [];
        $previousHash = $this->getGenesisHash();
        $blocksVerified = 0;

        foreach ($blocks as $blockHash) {
            $blockLogs = AuditLog::where('block_hash', $blockHash)
                ->orderBy('id')
                ->get();

            $blockData = $this->buildBlockData($blockLogs);
            $expectedHash = $this->generateHash($previousHash, $blockData);

            if ($expectedHash !== $blockHash) {
                $errors[] = [
                    'block_hash' => $blockHash,
                    'expected_hash' => $expectedHash,
                    'error' => 'Hash mismatch - potential tampering detected',
                ];
            }

            $previousHash = $blockHash;
            $blocksVerified++;
        }

        return [
            'valid' => empty($errors),
            'blocks_verified' => $blocksVerified,
            'errors' => $errors,
        ];
    }

    /**
     * Verify a specific audit record
     */
    public function verifyAuditRecord(AuditLog $log): bool
    {
        if (!$log->block_hash) {
            return true; // Unsealed records are mutable
        }

        $blockLogs = AuditLog::where('block_hash', $log->block_hash)
            ->orderBy('id')
            ->get();

        $previousBlock = AuditLog::where('id', '<', $blockLogs->first()->id)
            ->whereNotNull('block_hash')
            ->orderBy('id', 'desc')
            ->first();

        $previousHash = $previousBlock ? $previousBlock->block_hash : $this->getGenesisHash();
        $blockData = $this->buildBlockData($blockLogs);
        $expectedHash = $this->generateHash($previousHash, $blockData);

        return $expectedHash === $log->block_hash;
    }

    /**
     * Get chain statistics
     */
    public function getChainStats(): array
    {
        return [
            'total_records' => AuditLog::count(),
            'sealed_records' => AuditLog::whereNotNull('block_hash')->count(),
            'unsealed_records' => AuditLog::whereNull('block_hash')->count(),
            'total_blocks' => AuditLog::whereNotNull('block_hash')->distinct('block_hash')->count('block_hash'),
            'last_seal_time' => AuditLog::whereNotNull('sealed_at')->max('sealed_at'),
            'last_block_hash' => $this->getLastBlockHash(),
        ];
    }

    /**
     * Build canonical block data from logs
     */
    private function buildBlockData(Collection $logs): array
    {
        return $logs->map(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'user_id' => $log->user_id,
            'metadata' => $log->metadata,
            'created_at' => $log->created_at->toIso8601String(),
        ])->toArray();
    }

    /**
     * Get the genesis hash (first block)
     */
    private function getGenesisHash(): string
    {
        return hash('sha256', 'PANETA_AUDIT_CHAIN_GENESIS_' . config('app.key'));
    }

    /**
     * Get last sealed block hash
     */
    private function getLastBlockHash(): string
    {
        $cached = Cache::get(self::CACHE_KEY_LAST_HASH);
        
        if ($cached) {
            return $cached;
        }

        $lastSealed = AuditLog::whereNotNull('block_hash')
            ->orderBy('id', 'desc')
            ->first();

        return $lastSealed ? $lastSealed->block_hash : $this->getGenesisHash();
    }

    /**
     * Store block hash in cache
     */
    private function storeBlockHash(string $hash): void
    {
        Cache::put(self::CACHE_KEY_LAST_HASH, $hash, now()->addDays(30));
    }
}

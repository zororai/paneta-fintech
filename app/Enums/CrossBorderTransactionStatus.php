<?php

namespace App\Enums;

enum CrossBorderTransactionStatus: string
{
    case PENDING = 'pending';
    case FX_LOCKED = 'fx_locked';
    case SOURCE_DEBITED = 'source_debited';
    case FX_EXECUTED = 'fx_executed';
    case DESTINATION_CREDITED = 'destination_credited';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case ROLLED_BACK = 'rolled_back';

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::PENDING => in_array($newStatus, [self::FX_LOCKED, self::FAILED]),
            self::FX_LOCKED => in_array($newStatus, [self::SOURCE_DEBITED, self::FAILED, self::ROLLED_BACK]),
            self::SOURCE_DEBITED => in_array($newStatus, [self::FX_EXECUTED, self::FAILED, self::ROLLED_BACK]),
            self::FX_EXECUTED => in_array($newStatus, [self::DESTINATION_CREDITED, self::FAILED, self::ROLLED_BACK]),
            self::DESTINATION_CREDITED => in_array($newStatus, [self::COMPLETED, self::FAILED]),
            self::COMPLETED => false,
            self::FAILED => in_array($newStatus, [self::ROLLED_BACK]),
            self::ROLLED_BACK => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::ROLLED_BACK]);
    }

    public function requiresRollback(): bool
    {
        return in_array($this, [
            self::FX_LOCKED,
            self::SOURCE_DEBITED,
            self::FX_EXECUTED,
            self::DESTINATION_CREDITED,
        ]);
    }

    public function getCompletedLegs(): array
    {
        return match ($this) {
            self::PENDING => [],
            self::FX_LOCKED => ['fx_quote'],
            self::SOURCE_DEBITED => ['fx_quote', 'source_debit'],
            self::FX_EXECUTED => ['fx_quote', 'source_debit', 'fx_conversion'],
            self::DESTINATION_CREDITED => ['fx_quote', 'source_debit', 'fx_conversion', 'destination_credit'],
            self::COMPLETED => ['fx_quote', 'source_debit', 'fx_conversion', 'destination_credit', 'confirmation'],
            self::FAILED, self::ROLLED_BACK => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::FX_LOCKED => 'FX Rate Locked',
            self::SOURCE_DEBITED => 'Source Debited',
            self::FX_EXECUTED => 'FX Executed',
            self::DESTINATION_CREDITED => 'Destination Credited',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::ROLLED_BACK => 'Rolled Back',
        };
    }
}

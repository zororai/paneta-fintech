<?php

namespace App\Enums;

enum FxOfferStatus: string
{
    case OPEN = 'open';
    case PARTIALLY_FILLED = 'partially_filled';
    case MATCHED = 'matched';
    case EXECUTED = 'executed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case FAILED = 'failed';

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::OPEN => in_array($newStatus, [
                self::PARTIALLY_FILLED,
                self::MATCHED,
                self::CANCELLED,
                self::EXPIRED,
            ]),
            self::PARTIALLY_FILLED => in_array($newStatus, [
                self::MATCHED,
                self::CANCELLED,
                self::EXPIRED,
            ]),
            self::MATCHED => in_array($newStatus, [self::EXECUTED, self::FAILED]),
            self::EXECUTED => false,
            self::CANCELLED => false,
            self::EXPIRED => false,
            self::FAILED => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::EXECUTED,
            self::CANCELLED,
            self::EXPIRED,
            self::FAILED,
        ]);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::OPEN, self::PARTIALLY_FILLED]);
    }

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::PARTIALLY_FILLED => 'Partially Filled',
            self::MATCHED => 'Matched',
            self::EXECUTED => 'Executed',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
            self::FAILED => 'Failed',
        };
    }
}

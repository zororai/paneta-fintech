<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case EXECUTED = 'executed';
    case FAILED = 'failed';

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::PENDING => in_array($newStatus, [self::CONFIRMED, self::FAILED]),
            self::CONFIRMED => in_array($newStatus, [self::EXECUTED, self::FAILED]),
            self::EXECUTED => false,
            self::FAILED => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::EXECUTED, self::FAILED]);
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::CONFIRMED => 'Confirmed',
            self::EXECUTED => 'Executed',
            self::FAILED => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::CONFIRMED => 'blue',
            self::EXECUTED => 'green',
            self::FAILED => 'red',
        };
    }
}

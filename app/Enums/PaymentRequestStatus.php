<?php

namespace App\Enums;

enum PaymentRequestStatus: string
{
    case PENDING = 'pending';
    case PARTIALLY_FULFILLED = 'partially_fulfilled';
    case COMPLETED = 'completed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::PENDING => in_array($newStatus, [
                self::PARTIALLY_FULFILLED,
                self::COMPLETED,
                self::EXPIRED,
                self::CANCELLED,
            ]),
            self::PARTIALLY_FULFILLED => in_array($newStatus, [
                self::COMPLETED,
                self::EXPIRED,
                self::CANCELLED,
            ]),
            self::COMPLETED => false,
            self::EXPIRED => false,
            self::CANCELLED => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::EXPIRED, self::CANCELLED]);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::PENDING, self::PARTIALLY_FULFILLED]);
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PARTIALLY_FULFILLED => 'Partially Fulfilled',
            self::COMPLETED => 'Completed',
            self::EXPIRED => 'Expired',
            self::CANCELLED => 'Cancelled',
        };
    }
}

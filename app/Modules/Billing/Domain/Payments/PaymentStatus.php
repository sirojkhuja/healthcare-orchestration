<?php

namespace App\Modules\Billing\Domain\Payments;

enum PaymentStatus: string
{
    case INITIATED = 'initiated';
    case PENDING = 'pending';
    case CAPTURED = 'captured';
    case FAILED = 'failed';
    case CANCELED = 'canceled';
    case REFUNDED = 'refunded';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::FAILED, self::CANCELED, self::REFUNDED], true);
    }
}

<?php

namespace App\Modules\Pharmacy\Domain\Prescriptions;

enum PrescriptionStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case DISPENSED = 'dispensed';
    case CANCELED = 'canceled';

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
        return in_array($this, [self::DISPENSED, self::CANCELED], true);
    }
}

<?php

namespace App\Modules\Lab\Domain\LabOrders;

enum LabOrderStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case SPECIMEN_COLLECTED = 'specimen_collected';
    case SPECIMEN_RECEIVED = 'specimen_received';
    case COMPLETED = 'completed';
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

    /**
     * @return list<string>
     */
    public static function reconcilable(): array
    {
        return [
            self::SENT->value,
            self::SPECIMEN_COLLECTED->value,
            self::SPECIMEN_RECEIVED->value,
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELED], true);
    }
}

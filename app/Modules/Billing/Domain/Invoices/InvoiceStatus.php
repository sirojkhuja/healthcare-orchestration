<?php

namespace App\Modules\Billing\Domain\Invoices;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case FINALIZED = 'finalized';
    case VOID = 'void';

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
        return $this === self::VOID;
    }
}

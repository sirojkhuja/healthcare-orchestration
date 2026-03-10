<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class RemoveInvoiceItemCommand
{
    public function __construct(
        public string $invoiceId,
        public string $itemId,
    ) {}
}

<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class VoidInvoiceCommand
{
    public function __construct(
        public string $invoiceId,
        public string $reason,
    ) {}
}

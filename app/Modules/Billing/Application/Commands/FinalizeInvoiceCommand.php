<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class FinalizeInvoiceCommand
{
    public function __construct(
        public string $invoiceId,
    ) {}
}

<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class DeleteInvoiceCommand
{
    public function __construct(
        public string $invoiceId,
    ) {}
}

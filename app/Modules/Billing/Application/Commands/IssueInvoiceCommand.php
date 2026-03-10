<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class IssueInvoiceCommand
{
    public function __construct(
        public string $invoiceId,
    ) {}
}

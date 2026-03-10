<?php

namespace App\Modules\Billing\Application\Queries;

final readonly class GetInvoiceQuery
{
    public function __construct(
        public string $invoiceId,
    ) {}
}

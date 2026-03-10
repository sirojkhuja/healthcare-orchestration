<?php

namespace App\Modules\Billing\Application\Queries;

final readonly class ListInvoiceItemsQuery
{
    public function __construct(
        public string $invoiceId,
    ) {}
}

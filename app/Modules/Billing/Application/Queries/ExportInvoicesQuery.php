<?php

namespace App\Modules\Billing\Application\Queries;

use App\Modules\Billing\Application\Data\InvoiceSearchCriteria;

final readonly class ExportInvoicesQuery
{
    public function __construct(
        public InvoiceSearchCriteria $criteria,
        public string $format,
    ) {}
}

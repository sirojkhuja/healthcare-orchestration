<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Queries\ListInvoicesQuery;
use App\Modules\Billing\Application\Services\InvoiceReadService;

final class ListInvoicesQueryHandler
{
    public function __construct(
        private readonly InvoiceReadService $invoiceReadService,
    ) {}

    /**
     * @return list<InvoiceData>
     */
    public function handle(ListInvoicesQuery $query): array
    {
        return $this->invoiceReadService->list($query->criteria);
    }
}

<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Queries\SearchInvoicesQuery;
use App\Modules\Billing\Application\Services\InvoiceReadService;

final class SearchInvoicesQueryHandler
{
    public function __construct(
        private readonly InvoiceReadService $invoiceReadService,
    ) {}

    /**
     * @return list<InvoiceData>
     */
    public function handle(SearchInvoicesQuery $query): array
    {
        return $this->invoiceReadService->search($query->criteria);
    }
}

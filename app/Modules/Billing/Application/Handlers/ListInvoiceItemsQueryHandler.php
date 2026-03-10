<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Data\InvoiceItemData;
use App\Modules\Billing\Application\Queries\ListInvoiceItemsQuery;
use App\Modules\Billing\Application\Services\InvoiceItemService;

final class ListInvoiceItemsQueryHandler
{
    public function __construct(
        private readonly InvoiceItemService $invoiceItemService,
    ) {}

    /**
     * @return list<InvoiceItemData>
     */
    public function handle(ListInvoiceItemsQuery $query): array
    {
        return $this->invoiceItemService->list($query->invoiceId);
    }
}

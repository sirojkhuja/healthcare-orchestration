<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Data\InvoiceExportData;
use App\Modules\Billing\Application\Queries\ExportInvoicesQuery;
use App\Modules\Billing\Application\Services\InvoiceReadService;

final class ExportInvoicesQueryHandler
{
    public function __construct(
        private readonly InvoiceReadService $invoiceReadService,
    ) {}

    public function handle(ExportInvoicesQuery $query): InvoiceExportData
    {
        return $this->invoiceReadService->export($query->criteria, $query->format);
    }
}

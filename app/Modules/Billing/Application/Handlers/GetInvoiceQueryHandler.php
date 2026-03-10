<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Queries\GetInvoiceQuery;
use App\Modules\Billing\Application\Services\InvoiceAdministrationService;

final class GetInvoiceQueryHandler
{
    public function __construct(
        private readonly InvoiceAdministrationService $invoiceAdministrationService,
    ) {}

    public function handle(GetInvoiceQuery $query): InvoiceData
    {
        return $this->invoiceAdministrationService->get($query->invoiceId);
    }
}

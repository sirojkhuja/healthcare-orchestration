<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\RemoveInvoiceItemCommand;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Services\InvoiceItemService;

final class RemoveInvoiceItemCommandHandler
{
    public function __construct(
        private readonly InvoiceItemService $invoiceItemService,
    ) {}

    public function handle(RemoveInvoiceItemCommand $command): InvoiceData
    {
        return $this->invoiceItemService->remove($command->invoiceId, $command->itemId);
    }
}

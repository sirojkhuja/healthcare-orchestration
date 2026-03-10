<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\AddInvoiceItemCommand;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Services\InvoiceItemService;

final class AddInvoiceItemCommandHandler
{
    public function __construct(
        private readonly InvoiceItemService $invoiceItemService,
    ) {}

    public function handle(AddInvoiceItemCommand $command): InvoiceData
    {
        return $this->invoiceItemService->add($command->invoiceId, $command->attributes);
    }
}

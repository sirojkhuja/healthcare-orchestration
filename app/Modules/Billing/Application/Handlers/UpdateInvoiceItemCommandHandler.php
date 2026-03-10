<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\UpdateInvoiceItemCommand;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Services\InvoiceItemService;

final class UpdateInvoiceItemCommandHandler
{
    public function __construct(
        private readonly InvoiceItemService $invoiceItemService,
    ) {}

    public function handle(UpdateInvoiceItemCommand $command): InvoiceData
    {
        return $this->invoiceItemService->update($command->invoiceId, $command->itemId, $command->attributes);
    }
}

<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\DeleteInvoiceCommand;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Services\InvoiceAdministrationService;

final class DeleteInvoiceCommandHandler
{
    public function __construct(
        private readonly InvoiceAdministrationService $invoiceAdministrationService,
    ) {}

    public function handle(DeleteInvoiceCommand $command): InvoiceData
    {
        return $this->invoiceAdministrationService->delete($command->invoiceId);
    }
}

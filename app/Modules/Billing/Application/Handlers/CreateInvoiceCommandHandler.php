<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\CreateInvoiceCommand;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Services\InvoiceAdministrationService;

final class CreateInvoiceCommandHandler
{
    public function __construct(
        private readonly InvoiceAdministrationService $invoiceAdministrationService,
    ) {}

    public function handle(CreateInvoiceCommand $command): InvoiceData
    {
        return $this->invoiceAdministrationService->create($command->attributes);
    }
}

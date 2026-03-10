<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\UpdateInvoiceCommand;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Services\InvoiceAdministrationService;

final class UpdateInvoiceCommandHandler
{
    public function __construct(
        private readonly InvoiceAdministrationService $invoiceAdministrationService,
    ) {}

    public function handle(UpdateInvoiceCommand $command): InvoiceData
    {
        return $this->invoiceAdministrationService->update($command->invoiceId, $command->attributes);
    }
}

<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\VoidInvoiceCommand;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Services\InvoiceWorkflowService;

final class VoidInvoiceCommandHandler
{
    public function __construct(
        private readonly InvoiceWorkflowService $invoiceWorkflowService,
    ) {}

    public function handle(VoidInvoiceCommand $command): InvoiceData
    {
        return $this->invoiceWorkflowService->void($command->invoiceId, $command->reason);
    }
}

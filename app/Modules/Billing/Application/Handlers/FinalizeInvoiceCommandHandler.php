<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\FinalizeInvoiceCommand;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Services\InvoiceWorkflowService;

final class FinalizeInvoiceCommandHandler
{
    public function __construct(
        private readonly InvoiceWorkflowService $invoiceWorkflowService,
    ) {}

    public function handle(FinalizeInvoiceCommand $command): InvoiceData
    {
        return $this->invoiceWorkflowService->finalize($command->invoiceId);
    }
}

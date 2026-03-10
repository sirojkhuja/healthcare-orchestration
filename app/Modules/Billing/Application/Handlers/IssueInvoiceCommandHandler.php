<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\IssueInvoiceCommand;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Services\InvoiceWorkflowService;

final class IssueInvoiceCommandHandler
{
    public function __construct(
        private readonly InvoiceWorkflowService $invoiceWorkflowService,
    ) {}

    public function handle(IssueInvoiceCommand $command): InvoiceData
    {
        return $this->invoiceWorkflowService->issue($command->invoiceId);
    }
}

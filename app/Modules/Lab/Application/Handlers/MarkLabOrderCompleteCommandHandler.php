<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Commands\MarkLabOrderCompleteCommand;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Services\LabOrderWorkflowService;

final class MarkLabOrderCompleteCommandHandler
{
    public function __construct(
        private readonly LabOrderWorkflowService $labOrderWorkflowService,
    ) {}

    public function handle(MarkLabOrderCompleteCommand $command): LabOrderData
    {
        return $this->labOrderWorkflowService->complete($command->orderId);
    }
}

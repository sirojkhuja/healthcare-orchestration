<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Commands\CancelLabOrderCommand;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Services\LabOrderWorkflowService;

final class CancelLabOrderCommandHandler
{
    public function __construct(
        private readonly LabOrderWorkflowService $labOrderWorkflowService,
    ) {}

    public function handle(CancelLabOrderCommand $command): LabOrderData
    {
        return $this->labOrderWorkflowService->cancel($command->orderId, $command->reason);
    }
}

<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Commands\SendLabOrderCommand;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Services\LabOrderWorkflowService;

final class SendLabOrderCommandHandler
{
    public function __construct(
        private readonly LabOrderWorkflowService $labOrderWorkflowService,
    ) {}

    public function handle(SendLabOrderCommand $command): LabOrderData
    {
        return $this->labOrderWorkflowService->send($command->orderId);
    }
}

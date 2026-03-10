<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Commands\MarkSpecimenCollectedCommand;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Services\LabOrderWorkflowService;

final class MarkSpecimenCollectedCommandHandler
{
    public function __construct(
        private readonly LabOrderWorkflowService $labOrderWorkflowService,
    ) {}

    public function handle(MarkSpecimenCollectedCommand $command): LabOrderData
    {
        return $this->labOrderWorkflowService->markSpecimenCollected($command->orderId);
    }
}

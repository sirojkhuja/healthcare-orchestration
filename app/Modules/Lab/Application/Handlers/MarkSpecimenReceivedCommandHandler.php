<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Commands\MarkSpecimenReceivedCommand;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Services\LabOrderWorkflowService;

final class MarkSpecimenReceivedCommandHandler
{
    public function __construct(
        private readonly LabOrderWorkflowService $labOrderWorkflowService,
    ) {}

    public function handle(MarkSpecimenReceivedCommand $command): LabOrderData
    {
        return $this->labOrderWorkflowService->markSpecimenReceived($command->orderId);
    }
}

<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Commands\DispensePrescriptionCommand;
use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Pharmacy\Application\Services\PrescriptionWorkflowService;

final class DispensePrescriptionCommandHandler
{
    public function __construct(
        private readonly PrescriptionWorkflowService $prescriptionWorkflowService,
    ) {}

    public function handle(DispensePrescriptionCommand $command): PrescriptionData
    {
        return $this->prescriptionWorkflowService->dispense($command->prescriptionId);
    }
}
